
# 🚀 MS_MFA

Direct invocation of Microsoft MFA application, with push notification or TOTP verification.

[![Build](https://img.shields.io/badge/build-passing-brightgreen)](#) 
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](#license)
[![Coverage](https://img.shields.io/badge/coverage-95%25-brightgreen)](#)

---

## 📚 Table of Contents
- [Features](#-features)
- [Two Metrhods Only](#%EF%B8%8F-two-methods-only)
- [How Does It Work](#-how-does-it-work)
- [Links](#-links)

---

## ✨ Features
- ✅ Easy to install and run
- ⚡ Unofficial: used with the same config on all MS tenant, yet unofficial
- 🔒 Built-in security: no parameters to be sanitized, no customization

---

## 🖼️ Two Methods only
<h1>> pushPopup</h1>

This method is to raise a popup on Authenticator app of the smartphone where the user is configured.

In detail, this part of the configuration is the one responsible for this behaviour, as said by the [Full Story].

Code snippet:
```bash
    <a:KeyValueOfstringstring>
      <a:Key>OverrideVoiceOtp</a:Key>
      <a:Value>false</a:Value>
    </a:KeyValueOfstringstring>
```

Out of the full XML configuration:
<p align="center">
  <img src="img/pushPopup.png" alt="Screenshot" width="800">
</p>

Sometimes, to be better investigated, this option does not popup on Authenticator, and the returned value is 'challenge'.

This means that the operation of MFA could not be completed, and it has to fallback on next option, which is checking the correctness of the TOTP associated to the entry on Authenticator.  Whilst the next method works in every circumstance, the initial one sometimes fail.

<h1>> checkTOTP</h1>
This method is intended to check the correctness of user TOTP, to be inserted in a webpage as an example.

In detail, this part of the configuration is the one responsible for this behaviour, and this is an original contribution.

Code snippet:
```bash
    <a:KeyValueOfstringstring>
      <a:Key>OverrideNumberMatchingWithOTP</a:Key>
      <a:Value>true</a:Value>
    </a:KeyValueOfstringstring>
```

Out of the full XML configuration:
<p align="center">
  <img src="img/checkTOTP.png" alt="Screenshot" width="800">
</p>


---

## 🧩 How Does It Work
First of all, you need to generate the 'secret' to be used when calling the methods.

To get it, use the PowerShell script named 'generate.ps1', in the 'powershell' directory.

This has to be run one time only, and it returns the 'secret' for a servicePrincipal indeed, which is the same on all Microsoft O365 tenant, identified by the '981f26a1-7f43-403b-a875-f8b09b8cd720' label.

The script:
```bash
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
```

After being connected, create the 'secret', linked to the ServicePrincipal. 

That's all you need to to to setup the environment.

You now need to know the 'tenantId' and the UPN of the user to execute a test.

You can check the functionality by executing the 'pushPopup.ps1' script, which will push the popup OR will answer you back 'challenge'. In this case, this means you can not push whilst you can check the TOTP [to be better investigated].

You can find two PHP codes in the 'PHP' directory; properly inserting corresponding values to 'clientSecret', 'tenantId' and 'email' variables, you are now able to execute the actions.


---

## Links
Full story [online](https://www.entraneer.com/blog/entra/authentication/transactional-mfa-entra-id) and [locally](docs/fullStory.pdf)

