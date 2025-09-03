Connect-MgGraph -Scopes 'Application.ReadWrite.All'
# Get ID of 'Entra Id MFA Notification Client' Service Principal
$servicePrincipalId = (Get-MgServicePrincipal -Filter "appid eq '981f26a1-7f43-403b-a875-f8b09b8cd720'").Id
 
$params = @{
	passwordCredential = @{
		displayName = "My Application MFA"
	}
}
 
# Create Client Secret onto client
$secret = Add-MgServicePrincipalPassword -ServicePrincipalId $servicePrincipalId -BodyParameter $params
$secret
