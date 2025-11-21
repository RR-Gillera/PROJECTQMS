# Forgot Password Functionality - SeQueueR

## Overview
The ForgotPassword.php page provides a secure password reset functionality for SeQueueR users.

## Features

### 🔐 Two-Step Password Reset Process

#### Step 1: Email Verification
- **Email Input**: Users enter their registered email address
- **Reset Method Selection**: Choose between email or SMS verification
- **Validation**: Email format validation and basic security checks
- **Instructions Sent**: Password reset instructions sent to user's email

#### Step 2: Password Reset
- **Verification Code**: 6-digit code sent to user's email/SMS
- **New Password**: Secure password creation with validation
- **Password Confirmation**: Ensures passwords match
- **Success Notification**: Confirmation of successful password reset

### 🎨 User Interface Features

#### Design Elements
- **Consistent Styling**: Matches SeQueueR design system
- **Responsive Layout**: Works on all device sizes
- **Smooth Animations**: Fade-in effects and transitions
- **Visual Feedback**: Clear success/error messages
- **Accessibility**: Proper labels and keyboard navigation

#### Form Validation
- **Email Validation**: Proper email format checking
- **Password Requirements**: Minimum 8 characters
- **Password Matching**: Confirmation field validation
- **Code Formatting**: Auto-format verification codes
- **Real-time Feedback**: Instant validation messages

### 🔧 Technical Features

#### Security Measures
- **Session Management**: Secure session handling
- **Input Sanitization**: XSS protection
- **CSRF Protection**: Form token validation (ready for implementation)
- **Rate Limiting**: Prevents brute force attacks (ready for implementation)

#### User Experience
- **Password Visibility Toggle**: Show/hide password fields
- **Auto-focus**: Smart field focusing
- **Resend Code**: Option to resend verification codes
- **Help Links**: Support contact information

## File Structure

```
Frontend/Personnel/
├── ForgotPassword.php          # Main forgot password page
├── Signin.php                  # Updated with forgot password link
└── FORGOT_PASSWORD_README.md   # This documentation
```

## Usage

### For Users
1. Click "Forgot Password?" on the login page
2. Enter your email address
3. Choose reset method (email/SMS)
4. Check your email for verification code
5. Enter verification code and new password
6. Confirm new password
7. Click "Reset Password"

### For Developers
1. **Backend Integration**: Replace TODO comments with actual database operations
2. **Email Service**: Implement email sending functionality
3. **SMS Service**: Add SMS verification capability
4. **Security**: Add CSRF tokens and rate limiting
5. **Database**: Create password reset tokens table

## Backend Implementation Notes

### Required Database Tables
```sql
CREATE TABLE password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Required Functions
- `sendPasswordResetEmail($email, $token)`
- `sendPasswordResetSMS($phone, $code)`
- `validateResetToken($token)`
- `updateUserPassword($email, $newPassword)`
- `generateSecureToken()`

## Security Considerations

### Current Implementation
- ✅ Input validation and sanitization
- ✅ Session-based state management
- ✅ Password strength requirements
- ✅ XSS protection

### Recommended Additions
- 🔄 CSRF token validation
- 🔄 Rate limiting for reset attempts
- 🔄 Secure token generation
- 🔄 Token expiration handling
- 🔄 Audit logging

## Customization

### Styling
- Modify CSS classes in the `<style>` section
- Update color scheme in Tailwind classes
- Adjust animations and transitions

### Functionality
- Add additional reset methods
- Implement custom validation rules
- Add multi-language support
- Integrate with external services

## Testing

### Manual Testing Checklist
- [ ] Email validation works correctly
- [ ] Password requirements are enforced
- [ ] Verification code input works
- [ ] Password confirmation validation
- [ ] Success/error messages display
- [ ] Responsive design on mobile
- [ ] Accessibility features work

### Automated Testing
- Unit tests for validation functions
- Integration tests for form submission
- Security tests for input handling
- UI tests for user interactions

## Support

For technical support or questions about the forgot password functionality:
- **Email**: support@uc.edu.ph
- **Phone**: +63-32-123-4567
- **Documentation**: This README file
