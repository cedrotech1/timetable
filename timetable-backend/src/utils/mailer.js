const ejs = require("ejs");
const path = require("path");
const nodemailer = require("nodemailer");

class Email {
  constructor(user, claim = null, url = null, booking = null, reason = null) {
    this.to = user.email;
    this.firstname = user.names || user.firstname; // Use names field, fallback to firstname for compatibility
    this.password = user.password;
    this.email = user.email;
    this.from = process.env.EMAIL_FROM || 'ingbnelly@gmail.com'; // Use environment variable
    this.url = url;
    this.message = claim ? claim.message : '';  // Default empty message if not provided
   
  }

  createTransport() {
    const smtpHost = process.env.SMTP_HOST || '';
    const smtpPort = parseInt(process.env.SMTP_PORT, 10) || 25;
    const smtpUser = process.env.SMTP_USER || '';
    const smtpPass = process.env.SMTP_PASSWORD || '';
    const smtpSecure = (process.env.SMTP_SECURE || '').toLowerCase();

    if (smtpHost) {
      const opts = {
        host: smtpHost,
        port: smtpPort,
        secure: smtpSecure === 'ssl',
        tls: { rejectUnauthorized: false },
      };
      if (smtpSecure === 'tls') {
        opts.requireTLS = true;
      }
      if (smtpUser && smtpPass) {
        opts.auth = { user: smtpUser, pass: smtpPass };
      }
      return nodemailer.createTransport(opts);
    }

    // Legacy Gmail fallback
    return nodemailer.createTransport({
      service: 'gmail',
      auth: {
        user: process.env.GMAIL_USER || 'ingbnelly@gmail.com',
        pass: process.env.GMAIL_APP_PASSWORD || 'eojr ownl bsaa wkek',
      },
    });
  }

  static _emailSettingsCache = { value: null, at: 0 };

  static clearEmailSettingsCache() {
    Email._emailSettingsCache = { value: null, at: 0 };
  }

  async isEmailAllowed() {
    if (process.env.EMAIL_ENABLED !== 'true') {
      return false;
    }

    const cache = Email._emailSettingsCache;
    if (cache.value !== null && Date.now() - cache.at < 60000) {
      return cache.value;
    }

    try {
      const { getSystemSettings } = await import('../services/systemSettingsService.js');
      const settings = await getSystemSettings();
      const allowed = settings?.emailNotification !== 'disabled';
      Email._emailSettingsCache = { value: allowed, at: Date.now() };
      return allowed;
    } catch (error) {
      console.error('Could not load email notification settings:', error.message);
      return true;
    }
  }

  // Send the actual email
  // opts.plainText — optional plain-text alternative (when message is HTML)
  // opts.system — 'hostel' | 'reporting' (email branding)
  async send(template, subject, title, opts = {}) {
    const emailAllowed = await this.isEmailAllowed();

    if (!emailAllowed) {
      console.log(`📧 EMAIL DISABLED - Skipping email to ${this.to}: ${subject}`);
      return;
    }

    console.log(`📧 SENDING EMAIL to ${this.to}: ${subject}`);
    
    const transporter = this.createTransport();
    const systemKey = String(opts.system || this.system || 'reporting').toLowerCase();
    const isHostel = systemKey === 'hostel' || systemKey === 'wars_hostel';
    const branding = isHostel
      ? {
          notificationTitle: 'Hostel Notification',
          systemName: 'Hostel Management System',
          footerName: 'UR — Hostel Management System',
          loginHint: 'For more information, please log in to the Hostel Management System to view details.',
          openButtonLabel: 'Open',
          fromName: 'UR Hostel Management System',
          fromNameNoReply: 'UR Hostel (No Reply)',
        }
      : {
          notificationTitle: 'Reporting Notification',
          systemName: 'Reporting System',
          footerName: 'UR — Reporting System (WARS)',
          loginHint: 'For more information, please log in to the Reporting System to view details.',
          openButtonLabel: 'Open report',
          fromName: 'UR Reporting System',
          fromNameNoReply: 'UR Reporting (No Reply)',
        };

    // 1) Render HTML based on an ejs template
    const html = await ejs.renderFile(
      path.join(__dirname, `./../views/email/${template}.ejs`),
      {
        firstname: this.firstname,
        password: this.password,
        email: this.email,
        url: this.url || null,
        message: this.message,
        notificationTitle: branding.notificationTitle,
        systemName: branding.systemName,
        footerName: branding.footerName,
        loginHint: branding.loginHint,
        openButtonLabel: branding.openButtonLabel,
      }
    );

    // 2) Define email options
    const noReply = process.env.EMAIL_NO_REPLY !== 'false';
    const replyTo = process.env.EMAIL_REPLY_TO
      || (noReply && this.from.includes('@')
        ? `noreply@${this.from.split('@')[1]}`
        : '');

    const plain =
      opts.plainText ||
      String(title || '')
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim() ||
      subject;

    const mailOptions = {
      to: this.to,
      from: {
        name: noReply ? branding.fromNameNoReply : branding.fromName,
        address: this.from,
      },
      subject,
      text: plain,
      html,
    };

    if (noReply && replyTo) {
      mailOptions.replyTo = {
        name: 'Do Not Reply',
        address: replyTo,
      };
      mailOptions.headers = {
        'X-Auto-Response-Suppress': 'All',
        Precedence: 'bulk',
        'Auto-Submitted': 'auto-generated',
      };
    }

    // 3) Send email
    try {
      await transporter.sendMail(mailOptions);
      console.log(`✅ Email sent successfully to ${this.to}`);
    } catch (error) {
      console.error(`❌ Email failed to send to ${this.to}:`, error.message);
    }
  }

  async sendAccountAdded() {
    await this.send("accountAdded", "Welcome! Now", "Welcome to our service.", { system: 'reporting' });
  }

  async sendNotification() {
    await this.send("Notification", "Notification", "Notification", { system: 'reporting' });
  }

  async sendReportNotification(subject, message, plainText, system = 'reporting') {
    // Override the message for this specific email (may be HTML)
    const originalMessage = this.message;
    this.message = message;
    
    await this.send("Notification", subject, message, { plainText, system });
    
    // Restore original message
    this.message = originalMessage;
  }


  async sendResetPasswordCode() {
    await this.send("ResetPasswordCode", "Your Reset Password Code", "Here is your reset password code.", { system: 'reporting' });
  }
}

module.exports = Email;
