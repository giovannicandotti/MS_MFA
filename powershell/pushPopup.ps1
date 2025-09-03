$secret = "..."
$email = "name@domain.extension"
$tenantId = "..."
$clientId = "981f26a1-7f43-403b-a875-f8b09b8cd720" # this is the same for everyone
 
Write-Host "Get MFA Client Access Token"
$body = @{
    'resource'      = 'https://adnotifications.windowsazure.com/StrongAuthenticationService.svc/Connector'
    'client_id'     = $clientId
    'client_secret' = $secret
    'grant_type'    = "client_credentials"
    'scope'         = "openid"
}
 
$mfaClientToken = Invoke-RestMethod -Method post -Uri "https://login.microsoftonline.com/$tenantId/oauth2/token" -Body $body
Write-Host "Done."
 
 
Write-Host "Send MFA challenge to the user $email"
$XML = @"
<BeginTwoWayAuthenticationRequest>
	<Version>1.0</Version>
	<UserPrincipalName>$email</UserPrincipalName>
	<Lcid>en-us</Lcid>
	<AuthenticationMethodProperties
		xmlns:a="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
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
	<ContextId>bb07a24c-e5dc-4983-afe7-a0fcdc049cf7</ContextId>
	<SyncCall>true</SyncCall>
	<RequireUserMatch>true</RequireUserMatch>
	<CallerName>radius</CallerName>
	<CallerIP>UNKNOWN:</CallerIP>
</BeginTwoWayAuthenticationRequest>
"@
 
$headers = @{ "Authorization" = "Bearer $($mfaClientToken.access_token)" }
$mfaResult = Invoke-RestMethod -uri 'https://strongauthenticationservice.auth.microsoft.com/StrongAuthenticationService.svc/Connector//BeginTwoWayAuthentication' -Method POST -Headers $Headers -Body $XML -ContentType 'application/xml'
Write-Host "Done."

Write-Host $mfaResult.OuterXml
 
$mfaChallengeReceived = $mfaResult.BeginTwoWayAuthenticationResponse.AuthenticationResult
$mfaChallengeApproved = $mfaResult.BeginTwoWayAuthenticationResponse.Result.Value -eq "Success"
$mfaChallengeDenied = $mfaResult.BeginTwoWayAuthenticationResponse.Result.Value -eq "PhoneAppDenied"
$mfaChallengeTimeout = $mfaResult.BeginTwoWayAuthenticationResponse.Result.Value -eq "PhoneAppNoResponse"
$mfaChallengeMessage = $mfaResult.BeginTwoWayAuthenticationResponse.Result.Message
 
Write-Host $mfaChallengeMessage
 
if($mfaChallengeReceived -eq $true) { 
	if($mfaChallengeApproved -eq $true){
    	Write-Host "User Approved MFA Request"
	}
    if($mfaChallengeDenied -eq $true){
        Write-Host "User Denied Request"
    }
}
if($mfaChallengeReceived -eq $false) { 
	Write-Host "MFA challenge NOT received"
}
if($mfaChallengeReceived -eq "challenge") {
    Write-Host "MFA --> Challenge."
	Write-Host $mfaResult.OuterXml
	Write-Host $mfaResult.BeginTwoWayAuthenticationResponse
}
