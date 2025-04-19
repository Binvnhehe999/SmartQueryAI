# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| Latest   | :white_check_mark: |
| Patch - Fix - Release | :white_check_mark: |
| From 6.x.x   | :white_check_mark: |
| Below 6.0.0   | :x:                |

*we encourage you to update your chatbot to the latest version when possible*

## Reporting a Vulnerability

# Reporting a Security Vulnerability in SmartQuery AI

The SmartQuery AI team (owned by Binvnhehe999) takes security vulnerabilities seriously. We appreciate your efforts to responsibly disclose your findings, and we will make every effort to acknowledge your contributions.

If you believe you have found a security vulnerability in SmartQuery AI, please report it to us as described below.

## Scope

This policy applies to the SmartQuery AI project code and its associated components managed within the `SmartQueryAI-main` repository. This includes, but is not limited to:

*   The core PHP application code (e.g., `index.php`, `assets/message.php`, `assets/admin.php`, `assets/login.php`, `assets/ordercode.php`, `assets/endpoint.php`).
*   The installation process (`assets/install.php`).
*   Configuration files (`assets/config.yml`, `assets/custom.json`, `assets/prefix.json`, `.env` if used).
*   Database interactions and potential SQL injection vectors (primarily via `assets/db.php`, `assets/admin.php`, `assets/message.php`, `assets/ordercode.php`).
*   Authentication and session management (`assets/login.php`, `assets/admin.php`).
*   Interaction with external APIs like Google Gemini (e.g., API key exposure in `assets/gemini.php` or improper handling in `assets/message.php`).
*   File upload handling (if `enable_file_upload` is true).
*   Cross-Site Scripting (XSS) vulnerabilities in the chat interface or admin panel.
*   Information exposure (e.g., via `server_monitor.php`, or if `information.php` / `check_env.php` were not deleted as advised).
*   Access control issues in the admin panel.
*   Vulnerabilities in the confirmation code system (`assets/ordercode.php`, `assets/message.php`).

**Out of Scope:**

*   Vulnerabilities in third-party dependencies (please report those to the respective projects).
*   Vulnerabilities in the Google Gemini API itself (please report to Google).
*   Vulnerabilities in the underlying hosting environment, web server (Apache/Nginx), PHP runtime, or operating system, unless they are directly caused by a misconfiguration within the SmartQuery AI project itself.
*   Denial of Service (DoS/DDoS) attacks.
*   Social engineering or phishing attacks.
*   Reports of missing security best practices that do not lead to a direct, exploitable vulnerability (e.g., missing security headers) are welcome but may be treated as lower priority enhancements.
*   Vulnerabilities requiring physical access to the server.

## How to Report a Vulnerability

Please **do not** report security vulnerabilities through public GitHub issues, forums, or other public channels.

Instead, send an email to:

**ladyservant.official@gmail.com**  

**Please include the following details in your report:**

1.  **Type of Vulnerability:** e.g., Cross-Site Scripting (XSS), SQL Injection (SQLi), Remote Code Execution (RCE), Authentication Bypass, Information Disclosure, etc.
2.  **Location:** The specific file(s), function(s), or URL(s) affected.
3.  **Steps to Reproduce:** Provide clear, concise steps that allow us to reliably reproduce the vulnerability. Include any necessary code snippets, specific inputs, configuration settings, or HTTP requests.
4.  **Impact:** Briefly describe the potential impact of the vulnerability (e.g., data theft, service disruption, unauthorized access).
5.  **Proof of Concept (Optional but helpful):** A working exploit or code demonstrating the vulnerability.
6.  **Suggested Mitigation (Optional):** If you have ideas on how to fix the vulnerability, feel free to include them.

## Our Commitment (The Process)

1.  **Acknowledgement:** We aim to acknowledge receipt of your vulnerability report within 2-3 business days.
2.  **Validation:** We will investigate your report to confirm the vulnerability and its potential impact. This may involve asking for more information.
3.  **Remediation:** If the vulnerability is confirmed, we will work to develop and release a patch as quickly as possible, considering the severity and complexity.
4.  **Communication:** We will strive to keep you informed about the status of your report and the remediation progress.
5.  **Disclosure & Credit:** Once the vulnerability is fixed, we may coordinate public disclosure with you. We are happy to publicly acknowledge your contribution if you wish (e.g., in release notes or a Hall of Fame), unless you prefer to remain anonymous.

## Responsible Disclosure Guidelines

We ask that you follow these guidelines when reporting:

*   **Provide reasonable time:** Allow us a reasonable period (e.g., 90 days) to address the reported vulnerability before making any information public.
*   **Do no harm:** Act in good faith. Avoid actions that could disrupt the service, destroy data, or violate the privacy of others. Do not attempt to access, modify, or delete data that does not belong to you.
*   **Report promptly:** Report the vulnerability as soon as possible after discovery.
*   **Do not extort:** Do not demand payment or compensation in exchange for reporting the vulnerability.
*   **Confidentiality:** Keep the details of the vulnerability confidential until we have addressed it and agreed on a public disclosure timeline, if applicable.

## Safe Harbor

We consider security research and vulnerability reporting activities conducted consistent with this policy to be authorized and beneficial. We will not initiate legal action against researchers for vulnerability testing and reporting conducted in accordance with this policy. We request that you, in turn, comply with all applicable laws.

Thank you for helping keep SmartQuery AI secure!

---
