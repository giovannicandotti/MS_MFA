<?php
// Config
$tenantId     = "...";
$clientId     = "981f26a1-7f43-403b-a875-f8b09b8cd720"; // app globale MFA
$clientSecret = "...";
$email        = "name@domain.extension";

// --- 1) OAuth2 v1: client_credentials (resource) ---
$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/token";
$tokenBody = http_build_query([
    "resource"      => "https://adnotifications.windowsazure.com/StrongAuthenticationService.svc/Connector",
    "client_id"     => $clientId,
    "client_secret" => $clientSecret,
    "grant_type"    => "client_credentials",
    "scope"         => "openid",
]);

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $tokenBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/x-www-form-urlencoded"],
]);
$tokenResp = curl_exec($ch);
if ($tokenResp === false) {
    die("Errore token cURL: " . curl_error($ch) . PHP_EOL);
}
$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    die("Errore token HTTP ($httpCode): $tokenResp" . PHP_EOL);
}

$tokenJson = json_decode($tokenResp, true);
if (!isset($tokenJson["access_token"])) {
    die("Token non presente nella risposta: $tokenResp" . PHP_EOL);
}
$accessToken = $tokenJson["access_token"];

// --- 2) XML payload come nello script originale ---
$xml = <<<XML
<BeginTwoWayAuthenticationRequest>
  <Version>1.0</Version>
  <UserPrincipalName>{$email}</UserPrincipalName>
  <Lcid>en-us</Lcid>
  <AuthenticationMethodProperties xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
    <a:KeyValueOfstringstring>
      <a:Key>OverrideVoiceOtp</a:Key>
      <a:Value>false</a:Value>
    </a:KeyValueOfstringstring>
  </AuthenticationMethodProperties>
<ContextId>""" . guid() . """</ContextId>
  <SyncCall>true</SyncCall>
  <RequireUserMatch>true</RequireUserMatch>
  <CallerName>radius</CallerName>
  <CallerIP>UNKNOWN:</CallerIP>
</BeginTwoWayAuthenticationRequest>
XML;
//  <ContextId>bb07a24c-e5dc-4983-afe7-a0fcdc049cf7</ContextId>

// --- 3) Chiamata BeginTwoWayAuthentication ---
$mfaUrl = "https://strongauthenticationservice.auth.microsoft.com/StrongAuthenticationService.svc/Connector/BeginTwoWayAuthentication";
$ch = curl_init($mfaUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $xml,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/xml",
    ],
]);
$mfaResp = curl_exec($ch);
if ($mfaResp === false) {
    die("Errore MFA cURL: " . curl_error($ch) . PHP_EOL);
}
$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300) {
    die("Errore MFA HTTP ($httpCode): $mfaResp" . PHP_EOL);
}
echo "mfaResp:" . $mfaResp. PHP_EOL;

// --- 4) Parsing XML e logica come in PowerShell ---
$xmlObj = @simplexml_load_string($mfaResp);
if ($xmlObj === false) {
    // Alcune implementazioni restituiscono OuterXml come stringa;
    // in tal caso prova a stampare raw e uscire.
    echo "Risposta MFA (raw):" . PHP_EOL . $mfaResp . PHP_EOL;
    die("Impossibile parsare la risposta XML." . PHP_EOL);
}
echo "xmlObj: ".json_encode($xmlObj)."*".PHP_EOL;
echo "xmlObj->AuthenticationResult: ".$xmlObj->AuthenticationResult."*".PHP_EOL;


//$resp = $xmlObj->BeginTwoWayAuthenticationResponse ?? null;
$resp = $xmlObj ?? null;
if (!$resp) {
    echo "Risposta MFA (raw):" . PHP_EOL . $mfaResp . PHP_EOL;
    die("Struttura XML inattesa: nodo BeginTwoWayAuthenticationResponse mancante." . PHP_EOL);
}

$authenticationResult = (string)($resp->AuthenticationResult ?? "");
$resultValue          = (string)($resp->Result->Value ?? "");
$resultMessage        = (string)($resp->Result->Message ?? "");

echo "MFA Message: {$resultMessage}\n";

$approved = ($resultValue === "Success");
$denied   = ($resultValue === "PhoneAppDenied");
$timeout  = ($resultValue === "PhoneAppNoResponse");

if ($authenticationResult === "true") {
    if ($approved) {
        echo "User Approved MFA Request\n";
    }
    if ($denied) {
        echo "User Denied Request\n";
    }
}
if ($authenticationResult === "challenge") {
    echo "MFA --> Challenge.\n";
	echo json_encode((string)($resp));
}



function guid(): string {
  // RFC4122-ish random GUID
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}