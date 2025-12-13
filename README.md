# Elementor SMS OTP Login

A WordPress plugin that adds secure SMS-based one-time password (OTP) login functionality to Elementor Pro login forms, specifically designed for Georgian users using the smsoffice.ge SMS gateway.

![Version](https://img.shields.io/badge/version-1.0.4-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.8+-green.svg)
![PHP](https://img.shields.io/badge/php-7.4+-purple.svg)
![License](https://img.shields.io/badge/license-MIT-orange.svg)

## 🌟 Features

- **SMS-Based Authentication**: Secure login using one-time passwords sent via SMS
- **Elementor Pro Integration**: Seamlessly integrates with Elementor Pro login forms
- **Georgian Phone Support**: Validates and formats Georgian mobile numbers (995 country code)
- **OTP-Only Mode**: Option to disable traditional password login completely
- **Customizable Templates**: Fully customizable SMS message templates
- **Rate Limiting**: Built-in protection against SMS abuse with configurable limits
- **Comprehensive Logging**: Detailed SMS activity logs with export functionality
- **User-Friendly Admin**: Clean, intuitive settings interface
- **Multilingual Ready**: Translation-ready with text domain support
- **Responsive Design**: Works perfectly on all device sizes
- **Resend Functionality**: Users can request new codes with countdown timer
- **Code Expiry**: Configurable OTP expiration time (1-30 minutes)
- **Test SMS**: Built-in test functionality to verify configuration

## 📋 Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Elementor Pro (recommended for login forms)
- Active Smsoffice.ge account with API access
- Georgian mobile phone numbers for users

## 🚀 Installation

1. **Download the Plugin**
   ```bash
   git clone https://github.com/yourusername/elementor-sms-otp.git
   ```

2. **Upload to WordPress**
   - Upload the plugin folder to `/wp-content/plugins/`
   - Or install via WordPress admin: Plugins → Add New → Upload Plugin

3. **Activate the Plugin**
   - Navigate to Plugins in WordPress admin
   - Find "Elementor SMS OTP Login" and click Activate

4. **Configure API Credentials**
   - Go to SMS OTP → Settings in WordPress admin
   - Enter your Smsoffice.ge API key
   - Configure sender name and other settings

## ⚙️ Configuration

### Getting Started

1. **Obtain API Credentials**
   - Register at [smsoffice.ge](https://smsoffice.ge)
   - Navigate to your profile page
   - Copy your API key

2. **Register Sender Name**
   - At smsoffice.ge, register your desired sender name (max 11 characters)
   - This appears as the SMS sender to recipients

3. **Plugin Settings**
   Navigate to **SMS OTP → Settings** and configure:

   - **Enable SMS OTP**: Toggle to activate/deactivate the plugin
   - **OTP-Only Mode**: Disable password login (users can only use SMS OTP)
   - **API Key**: Your Smsoffice.ge API key
   - **Sender Name**: Registered sender name (max 11 chars)
   - **Code Expiry**: How long OTP codes remain valid (1-30 minutes)
   - **Rate Limit**: Maximum OTP requests per user per hour

4. **Customize Text**
   All front-end text can be customized including:
   - Button labels
   - Input placeholders
   - Status messages
   - SMS template

### SMS Template

Customize your SMS message using the `{code}` placeholder:

```
Your login code is {code}
```

Example customizations:
```
[YourSite] Your verification code: {code}
```
```
Welcome! Use code {code} to login. Valid for 5 minutes.
```

## 📱 Usage

### For Users

1. **Add Phone Number**
   - Navigate to WordPress Profile
   - Scroll to "SMS OTP Login" section
   - Enter Georgian mobile number (format: 5XXXXXXXX)
   - Save profile

2. **Login with SMS OTP**
   - Go to your site's login page (Elementor Pro form)
   - Enter username or email
   - Click "Login with SMS Code"
   - Enter the 6-digit code received via SMS
   - Click "Verify Code"

3. **Resend Code**
   - If code doesn't arrive, click "Resend code" after the countdown
   - Maximum requests limited by admin settings

### For Administrators

1. **View SMS Logs**
   - Navigate to SMS OTP → SMS Logs
   - View detailed activity with status indicators
   - Filter and search through logs

2. **Export Logs**
   - Click "Export Logs" to download log file
   - Logs exported as dated text files

3. **Test SMS Functionality**
   - Go to SMS OTP → Settings
   - Scroll to "Test SMS" section
   - Enter a test phone number
   - Click "Send Test SMS"

4. **Monitor Status**
   - Check plugin status in the sidebar
   - View API configuration status
   - Monitor log file size

## 🎨 Customization

### Styling

The plugin includes minimal, clean CSS that works with most themes. To customize:

```css
/* Customize OTP button color */
.elementor-otp-btn {
    background-color: #your-color !important;
}

/* Customize verify button */
.elementor-otp-verify {
    background-color: #your-color !important;
}

/* Customize input field */
.elementor-otp-input {
    border-color: #your-color;
}
```

### Hooks and Filters

Developers can extend functionality using WordPress hooks:

```php
// Modify phone validation
add_filter('elementor_sms_otp_validate_phone', function($is_valid, $phone) {
    // Custom validation logic
    return $is_valid;
}, 10, 2);

// Modify SMS content before sending
add_filter('elementor_sms_otp_sms_content', function($message, $otp_code, $user) {
    // Custom message formatting
    return $message;
}, 10, 3);
```

## 🔒 Security Features

- **Nonce Verification**: All AJAX requests protected with WordPress nonces
- **Rate Limiting**: Prevents SMS bombing attacks
- **Code Expiry**: Automatic invalidation of old codes
- **Secure Logging**: Protected logs directory with .htaccess
- **Input Sanitization**: All user inputs properly sanitized
- **IP Tracking**: Logs include IP addresses for security monitoring
- **Transient Storage**: OTP codes stored securely in WordPress transients

## 📊 Log Format

Logs include comprehensive information:

```
[2025-01-15 14:30:45] User: john_doe (ID: 5) | Phone: 5XXXXXXXX | OTP: 123456 | Status: SENT | IP: 192.168.1.1 | Agent: Mozilla/5.0...
```

Status indicators:
- 🟡 **SENT**: OTP successfully sent via SMS
- 🟢 **VERIFIED**: User successfully logged in with OTP
- 🔴 **FAILED**: SMS sending or verification failed
- ⚫ **EXPIRED**: OTP code expired before use

## 🌐 Supported Languages

- English (default)
- Georgian (partial - UI elements)

Translation files location: `/languages/`

To add translations, use POEdit or similar tools with the `elementor-sms-otp` text domain.

## ❓ FAQ

**Q: Does this work without Elementor Pro?**  
A: The plugin is designed for Elementor Pro login forms. Basic WordPress login forms are not currently supported.

**Q: Can I use this with non-Georgian phone numbers?**  
A: Currently, the plugin only supports Georgian phone numbers (995 country code) via smsoffice.ge. International support would require code modifications.

**Q: What happens if a user doesn't have a phone number?**  
A: They must add a phone number to their WordPress profile to use SMS OTP login. Traditional password login remains available unless OTP-Only mode is enabled.

**Q: How much do SMS messages cost?**  
A: SMS costs are determined by your smsoffice.ge account and pricing plan.

**Q: Can I customize the SMS message?**  
A: Yes! Go to SMS OTP → Settings and modify the "SMS Text Template" field. Use `{code}` as a placeholder for the OTP.

**Q: Is this plugin secure?**  
A: Yes. The plugin implements WordPress security best practices including nonces, input sanitization, rate limiting, and secure code storage.

**Q: Can I disable password login completely?**  
A: Yes. Enable "OTP-Only Mode" in settings to hide the password field and traditional login button.

## 🐛 Troubleshooting

### SMS Not Sending

1. Verify API key is correct
2. Check smsoffice.ge account balance
3. Ensure sender name is registered
4. Check logs for specific error codes

### Phone Number Invalid

- Format must be 9 digits starting with 5
- Example: 591234567
- Remove country code (995) and any special characters

### OTP Verification Fails

- Ensure code hasn't expired (check expiry settings)
- Verify user entered correct 6-digit code
- Check if rate limit was exceeded
- Review SMS logs for details

## 📝 Changelog

### Version 1.0.4
- Added customizable SMS template
- Improved error handling
- Enhanced logging system
- Added OTP-Only mode
- UI/UX improvements

### Version 1.0.0
- Initial release
- Basic SMS OTP functionality
- Elementor Pro integration
- Admin settings page
- SMS logging system

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**iko**
- Website: [iko.ge](https://iko.ge)
- Plugin URI: [https://iko.ge](https://iko.ge)

## 🙏 Acknowledgments

- Smsoffice.ge for SMS gateway services
- Elementor Pro for the excellent form builder
- WordPress community for continued support

## 📞 Support

For support and bug reports:
- Create an issue on GitHub
- Visit [iko.ge](https://iko.ge)
- Check the SMS logs for debugging information

---

**Note**: This plugin requires an active Smsoffice.ge account with sufficient balance to send SMS messages. SMS costs are determined by your Smsoffice.ge pricing plan.
