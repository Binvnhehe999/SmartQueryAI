# SmartQuery AI - Early Access

A modern, user-friendly chatbot and feedback assistant powered by AI.  
**SmartQuery AI** aims to enhance automated interactions and feedback loops with a sleek interface and open-source flexibility.

# Why choose SmartQueryAI project?

This project though is AI powered, it is capable of working without AI API (Ability to work even without internet), especially it is capable of running right on basic php web (The project is developed on popular Free Website Hosting)
---

## Table of Contents

- [License & Copyright](#license--copyright)
- [Installation](#installation)
- [Prompt AI Configuration](#prompt-ai-configuration)
- [Developer Information](#developer-information)
- [Security Notes](#security-notes)
- [Customization](#customization)
- [Support & Funding](#support--funding)

---

## License & Copyright

**Project**: SmartQuery AI  
**Author/Owner**: Binvnhehe999  
**License**: Dual License (GPLv3 + Commercial Donor License)  
**Date**: 29 June 2007

This project is dual-licensed:

- **GNU GENERAL PUBLIC LICENSE Version 3 (GPLv3)** – You are free to use, share, and modify this software under the terms of the GPL license.  
  See the included [`LICENSE`](./LICENSE) file for details.

- **Commercial Donor License** – For users who donate **$7.00 USD or more**, a commercial-use license is available.  
  This license allows commercial use, modification, and redistribution under specific conditions.

> See [`COMMERCIAL-LICENSE.md`](./COMMERCIAL-LICENSE.md) for full details.

> **Important Notice:**
> SmartQueryAI is licensed under the GPLv3 license, which allows you to use, modify, and distribute this software freely, **as long as you comply with the GPLv3 terms** (including making source code available for redistributed versions).
>
> However, if you wish to:
> - Use SmartQueryAI in **closed-source** or **commercial** projects **without releasing your modifications**
> - Distribute a modified version **without following GPLv3**
>
> You must obtain a **Commercial Donor License** by donating $7.00 USD or more via PayPal:  
> [https://paypal.me/MeoMongMo](https://paypal.me/MeoMongMo)
>
> You **purchased** this product on **Namelessmc.com**

> Without the commercial license, **you are required to follow GPLv3 strictly**.  
> Failure to comply with either license terms may result in the revocation of usage rights.
---

## Installation

To get started with SmartQuery AI:

1. **Set folder permissions**:
   ```bash
   chmod 755 assets css js
   ```

2. **Run installation script**:
   Visit your browser and go to:
   ```
   /assets/install.php
   ```

3. **Install dependencies**:
   Make sure you have [Composer](https://getcomposer.org/) installed, then run:
   ```bash
   composer install
   ```

---

## Prompt AI Configuration

> SmartQuery AI integrates with **Google Gemini API** for AI functionality.

### Default Setup

- The project uses **Google Gemini API** by default (free for development use).
- Ensure a stable internet connection for proper AI interaction.

### Customization Options

- **Change API integration**:  
  Modify `gemini.php` to point to your preferred AI API.

- **Edit AI prompt logic** *(Not recommended unless necessary)*:  
  Update logic and prompt flow in `message.php`.

---

## Developer Information

- **Current Version**: `6.6.3 Build 8` *(Early Access)*
- **Base Version**: `6.6.3_b7`
- **Last Modified**: `2025-04-06`

---

## Security Notes

Before making the chatbot publicly accessible, **remove the following files** for security:

- `information.php`
- `check_env.php`

These files may expose sensitive environment or server info.

---

## Customization

Configuration files can be found in the `assets/` directory:

- Modify UI behavior, themes, or interaction logic.
- Adjust `.env` for database and environment settings.

Make sure to keep backups of any changes you make.

---

## Support & Funding

Help keep this project alive!  
If you'd like to support further development, feel free to donate:

[**paypal.me/MeoMongMo**](https://paypal.me/MeoMongMo)

---

## Contact

For bugs, feature requests, or collaboration:

- GitHub Issues: https://github.com/Binvnhehe999/SmartQueryAI
- Email/DM via project page: ladyservant.official@gmail.com
- SmartQuery AI Discord: https://discord.gg/e6qnwBvAhC
