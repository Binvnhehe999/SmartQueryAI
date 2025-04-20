# SmartQuery AI - Early Access

A modern, user-friendly chatbot and feedback assistant powered by AI.  
**SmartQuery AI** aims to enhance automated interactions and feedback loops with a sleek interface and open-source flexibility.

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
**License**: GNU GENERAL PUBLIC LICENSE Version 3 (GPLv3)  
**Date**: 29 June 2007

This project is licensed under the **GPLv3** license.  
You are **free to share and modify** this software under the terms of the license. However:

> **You may NOT:**
> - Distribute or resell this project in any way.
> - Change the original code and publicly share or sell it.

For full license details, please refer to the included `LICENSE` file.

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
