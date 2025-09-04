
# 🚀 MS_MFA

Direct invocation of Microsoft MFA application, with push notification or TOTP verification.

[![Build](https://img.shields.io/badge/build-passing-brightgreen)](#) 
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](#license)
[![Coverage](https://img.shields.io/badge/coverage-95%25-brightgreen)](#)

---

## 📚 Table of Contents
- [Features](#-features)
- [How Does It Work](#-how-does-it-work)
- [Two Methods Only](#%EF%B8%8F-two-methods-only)
- [Links](#-links)

---

## ✨ Features
- ✅ Easy to install and run
- ⚡ Unofficial: used with the same config on all MS tenant, yet unofficial
- 🔒 Built-in security: no parameters to be sanitized, no customization

---

## 🧩 How Does It Work
First of all, you need to generate the 'secret' to be used when calling the methods.

To get it, use the PowerShell script named 'generate.ps1', in the 'powershell' directory.

This has to be run one time only, and it returns the 'secret' for a servicePrincipal indeed, which is the same on all Microsoft O365 tenant, identified by the '981f26a1-7f43-403b-a875-f8b09b8cd720' label.

The script to get the secret:
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

You can check the functionality by executing the 'pushPopup.ps1' script, which will push the popup when the 'Default sign-in method' of the user is 'Microsoft Authenticator notification'. If the answer is 'challenge', this means you can not push whilst you can check the TOTP; probably the 'Default sign-in method' is set to 'SMS (primary mobile)'.

You can find two PHP codes in the 'PHP' directory; properly inserting corresponding values to 'clientSecret', 'tenantId' and 'email' variables, you are now able to execute the methods described below.


---


## 🖼️ Two Methods only
<h1>> pushPopup</h1>

This method is to raise a popup on Authenticator app of the smartphone where the user is configured. It works properly when the 'Default sign-in method' of the user is 'Microsoft Authenticator notification'.

There are limitations in using this approach against the usage done through own Microsoft functions (e.g. login to portal), where geographical data can be included.

Here the question for the user is a simple 'Approve / Deny' question. In detail, this part of the configuration is the one responsible for this behaviour, as said by the [Full Story].

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


Result: 'Approved', 'Denied' or 'challenge'. The latter needs the following call to following "checkTOTP" method, as long as it's not possible to excecute an 'Apply/Deny' request.


<h1>> checkTOTP</h1>
This method is intended to check the correctness of user TOTP, to be inserted in a webpage as an example. It works properly as when the 'Default sign-in method' of the user is 'SMS (primary mobile)' or similar.

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


## Links
Full story [online](https://www.entraneer.com/blog/entra/authentication/transactional-mfa-entra-id) and [locally](docs/fullStory.pdf)

