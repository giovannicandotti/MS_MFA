<?php
/**
 * Minimal demo: handle the "challenge" branch for Entra MFA (OTP step).
 * - Requires: PHP CLI, cURL, SimpleXML, and (optionally) readline for OTP prompt.
 * - Assumes you already created a secret for the tenant's built-in "MFA Notification Client" app.
 */
$tenantId     = "...";
$clientId     = "981f26a1-7f43-403b-a875-f8b09b8cd720"; // app globale MFA
$clientSecret = "...";
$email        = "name@domain.extension";


/* ========= 1) OAuth2 token (v1, resource model) ========= */
$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/token";
$tokenBody = http_build_query([
  "resource"      => "https://adnotifications.windowsazure.com/StrongAuthenticationService.svc/Connector",
  "client_id"     => $clientId,
  "client_secret" => $clientSecret,
  "grant_type"    => "client_credentials",
  "scope"         => "openid",
]);
$accessToken = httpPostFormAndGetAccessToken($tokenUrl, $tokenBody);

/* ========= 2) BeginTwoWayAuthentication ========= */
$beginUrl = "https://strongauthenticationservice.auth.microsoft.com/StrongAuthenticationService.svc/Connector/BeginTwoWayAuthentication";
$beginXml = <<<XML
<BeginTwoWayAuthenticationRequest>
  <Version>1.0</Version>
  <UserPrincipalName>{$email}</UserPrincipalName>
  <Lcid>en-us</Lcid>
  <AuthenticationMethodProperties xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
    <a:KeyValueOfstringstring>
      <a:Key>OverrideVoiceOtp</a:Key>
      <a:Value>false</a:Value>
    </a:KeyValueOfstringstring>
    <!-- Force OTP instead of number matching; remove if you want push approve/deny -->
    <a:KeyValueOfstringstring>
      <a:Key>OverrideNumberMatchingWithOTP</a:Key>
      <a:Value>true</a:Value>
    </a:KeyValueOfstringstring>
  </AuthenticationMethodProperties>
  <ContextId>""" . guid() . """</ContextId>
  <SyncCall>true</SyncCall>
  <RequireUserMatch>true</RequireUserMatch>
  <CallerName>radius</CallerName>
  <CallerIP>UNKNOWN:</CallerIP>
</BeginTwoWayAuthenticationRequest>
XML;

$beginRespXml = httpPostXml($beginUrl, $accessToken, $beginXml);
$begin = parseBeginResponse($beginRespXml);

echo "[Begin] Result.Value={$begin['resultValue']} | AuthResult={$begin['authResult']} | Message={$begin['message']}\n";

if (strcasecmp($begin['authResult'], "challenge") !== 0) {
  // Not a challenge flow — handle Success/Denied/Timeout as usual
  if ($begin['approved']) {
    echo "MFA approved.\n";
    exit(0);
  } elseif ($begin['denied']) {
    echo "MFA denied by user.\n";
    exit(2);
  } else {
    echo "MFA not completed (NoResponse/Unknown). Message: {$begin['message']}\n";
    exit(3);
  }
}

/* ========= 3) Prompt for OTP ========= */
$otp = prompt("Enter OTP code from Authenticator: ");

/* ========= 4) EndTwoWayAuthentication ========= */
$affinity = $begin['affinityUrl'] ?: $begin['challengeUri'] ?: null;
$endBase  = $affinity ?: "https://strongauthenticationservice.auth.microsoft.com/StrongAuthenticationService.svc/Connector";
$endUrl   = rtrim($endBase, "/") . "/EndTwoWayAuthentication";
echo "END_URL: ".$endUrl.PHP_EOL;


$endXml = <<<XML
<EndTwoWayAuthenticationRequest>
  <Version>1.0</Version>
  <SessionId>{$begin['sessionId']}</SessionId>
  <ContextId>""" . guid() . """</ContextId>
  <AdditionalAuthData>{$otp}</AdditionalAuthData>
  <UserPrincipalName>{$email}</UserPrincipalName>
</EndTwoWayAuthenticationRequest>
XML;

$endRespXml = httpPostXml($endUrl, $accessToken, $endXml);
$end = parseEndResponse($endRespXml);

echo "[End] Result.Value={$end['resultValue']} | AuthResult={$end['authResult']} | Message={$end['message']}\n";

if ($end['approved']) {
  echo "MFA challenge completed successfully.\n";
  // --> place your protected action here
  exit(0);
} elseif ($end['denied']) {
  echo "MFA challenge denied by user.\n";
  exit(2);
} else {
  echo "MFA challenge failed or timed out. Message: {$end['message']}\n";
  exit(3);
}

/* ================= Helpers ================= */

function httpPostFormAndGetAccessToken(string $url, string $body): string {
  $resp = curl_post($url, ["Content-Type: application/x-www-form-urlencoded"], $body);
  $http = $resp['http_code']; $payload = $resp['body'];
  if ($http < 200 || $http >= 300) {
    throw new RuntimeException("Token HTTP $http: $payload");
  }
  $json = json_decode($payload, true);
  if (!isset($json['access_token'])) {
    throw new RuntimeException("access_token missing: $payload");
  }
  return $json['access_token'];
}

function httpPostXml(string $url, string $bearer, string $xmlBody): string {
  $headers = [
    "Authorization: Bearer {$bearer}",
    "Content-Type: application/xml",
  ];
  $resp = curl_post($url, $headers, $xmlBody);
  $http = $resp['http_code'];
  if ($http < 200 || $http >= 300) {
    throw new RuntimeException("POST XML HTTP $http: " . $resp['body']);
  }
  return $resp['body'];
}

function curl_post(string $url, array $headers, string $body): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 60,
  ]);
  $payload = curl_exec($ch);
  if ($payload === false) {
    $err = curl_error($ch);
    curl_close($ch);
    throw new RuntimeException("cURL error: $err");
  }
  $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  return ['http_code' => $http, 'body' => $payload];
}

function parseBeginResponse(string $xml): array {
  $x = tryXml($xml);
  $resp = $x->BeginTwoWayAuthenticationResponse ?? $x;
  $resV = (string)($resp->Result->Value ?? "");
  $msg  = (string)($resp->Result->Message ?? "");
  $auth = (string)($resp->AuthenticationResult ?? "");
  $sid  = (string)($resp->SessionId ?? "");
  $aff  = (string)($resp->AffinityUrl ?? "");
  $curl = (string)($resp->ChallengeUri ?? "");
  return [
    'resultValue' => $resV,
    'message'     => $msg,
    'authResult'  => $auth,
    'sessionId'   => $sid,
    'affinityUrl' => $aff,
    'challengeUri'=> $curl,
    'approved'    => strcasecmp($resV, "Success") === 0,
    'denied'      => strcasecmp($resV, "PhoneAppDenied") === 0,
    'timeout'     => strcasecmp($resV, "PhoneAppNoResponse") === 0,
  ];
}

function parseEndResponse(string $xml): array {
  $x = tryXml($xml);
  $resp = $x->EndTwoWayAuthenticationResponse ?? $x;
  $resV = (string)($resp->Result->Value ?? "");
  $msg  = (string)($resp->Result->Message ?? "");
  $auth = (string)($resp->AuthenticationResult ?? "");
  return [
    'resultValue' => $resV,
    'message'     => $msg,
    'authResult'  => $auth,
    'approved'    => (strcasecmp($resV, "Success") === 0) || (strcasecmp($auth, "true") === 0),
    'denied'      => strcasecmp($resV, "PhoneAppDenied") === 0,
    'timeout'     => strcasecmp($resV, "PhoneAppNoResponse") === 0,
  ];
}

function tryXml(string $xml) {
  libxml_use_internal_errors(true);
  $x = simplexml_load_string($xml);
  if ($x === false) {
    throw new RuntimeException("Invalid XML:\n$xml");
  }
  return $x;
}

function prompt(string $msg): string {
  if (function_exists('readline')) {
    $val = trim(readline($msg));
  } else {
    echo $msg;
    $val = trim(fgets(STDIN));
  }
  if ($val === '') {
    throw new RuntimeException("Empty OTP provided.");
  }
  return $val;
}

function guid(): string {
  // RFC4122-ish random GUID
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}