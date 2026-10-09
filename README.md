# 1NCE Management & WooCommerce Integration

A WordPress & WooCommerce plugin for managing 1NCE IoT SIM cards, monitoring data/SMS quotas, alerting customers when limits are reached, and automating SIM top-ups through WooCommerce orders.

---

## Features

- **1NCE API Integration**: Connects securely to the 1NCE REST API using OAuth2 credentials to retrieve SIM card details, data quota, and SMS quota.
- **ICCID Management**:
    - Add, edit, search, and delete registered SIM ICCIDs directly from the WordPress Admin dashboard.
    - Set custom quota threshold limits (Data in MB and SMS count) per SIM card.
    - Track SIM status, remaining quota, last sync time, and notification history.
- **Automated Synchronization**:
    - Background cron job to sync SIM card status and quotas at scheduled intervals (Hourly, Twice Daily, Daily, or Manual).
    - Manual bulk and single SIM sync capabilities.
- **Quota Alerts & Notifications**:
    - Automated threshold monitoring for Data (MB) and SMS quota.
    - Email alerts and SMS notifications via custom customizable templates with placeholder support (`{name}`, `{iccid}`, `{quotaMB}`, `{quotaSMS}`).
    - Configurable notification frequency and maximum notification limits to prevent spamming.
- **WooCommerce Integration**:
    - Require ICCID input on specific renewal / top-up WooCommerce products.
    - Client-side and server-side real-time ICCID validation against 1NCE API before adding products to the cart.
    - Automatically restricts ICCID products to quantity 1 per item and prevents duplicate cart items.
    - Attaches the ICCID to order items and metadata.
    - **Automated SIM Top-Up**: Automatically triggers SIM quota top-up through the 1NCE API upon completed payment.
    - Post-renewal verification cron to confirm quota balance after renewal.
- **Frontend Shortcode**:
    - Use `[1nce]` on any page or post to provide a public or customer-facing SIM status and quota lookup form.

---

## Requirements

- **WordPress**: 5.8 or higher
- **WooCommerce**: 5.0 or higher (optional, required only for top-up / purchasing features)
- **PHP**: 7.4 or higher (cURL and JSON extensions enabled)
- **1NCE Account**: Active 1NCE account with API credentials (Client ID and Client Secret)

---

## Installation

### Method 1: Manual Upload (ZIP)

1. Download the plugin repository as a `.zip` archive (or clone it).
2. Log in to your WordPress Admin dashboard.
3. Navigate to **Plugins > Add New > Upload Plugin**.
4. Choose the plugin `.zip` file and click **Install Now**.
5. Click **Activate Plugin**.

### Method 2: Manual Directory Installation

1. Clone or extract the plugin files into your WordPress plugins directory:
   ```bash
   wp-content/plugins/gnce/
   ```
2. In the WordPress Admin dashboard, go to **Plugins > Installed Plugins**.
3. Locate **1NCE management** and click **Activate**.

---

## Configuration

Once activated, navigate to the **1NCE > Settings** menu in your WordPress Admin dashboard:

### 1. API Settings

- **Client ID**: Your 1NCE API Client ID.
- **Client Secret**: Your 1NCE API Client Secret.
- **Payment Method**: Select the default payment method to use for automated renewals via API (Credit Card, Bank Transfer, Monthly Invoice, Boleto).

### 2. Sync & Notifications

- **API Sync Interval**: Frequency for syncing SIM cards via background cron (Hourly, Twice Daily, Daily, Never).
- **Threshold (MB)**: Global default remaining data limit (in MB) below which low quota notifications are triggered during sync.
- **Threshold (SMS)**: Global default remaining SMS count limit below which low quota notifications are triggered during sync.
- **Notification Frequency**: How often to send threshold alerts for the same SIM card (Daily, Every 3 Days, Weekly, Never).
- **Notification Limit**: Maximum number of notifications to send per SIM card when a threshold is breached.
- **Quota Verification Interval**: Delay after a successful order to verify updated quotas from 1NCE (1 Hour, 2 Hours, 4 Hours, Never).
- **Enable Debug Logging**: Toggle writing debug logs to `gnce_debug.log`.

### 3. Templates

- **Email Subject**: Subject line for low quota email alerts.
- **Email Body**: Content of the email notification.
- **SMS Text**: Content of the SMS notification.
- *Available placeholders*: `{name}`, `{iccid}`, `{quotaMB}`, `{quotaSMS}`.

---

## Usage

### 1. Managing ICCIDs

- Navigate to **1NCE > ICCIDs** to view all saved SIM cards with current data/SMS quotas and sync statuses.
- Click **Add New** (or go to **1NCE > Add ICCID**) to register a new SIM card. You can configure individual notification preferences and optionally enable custom Threshold (MB) / Threshold (SMS) overrides (disabled by default, falling back to the global threshold settings).
- Perform single or bulk actions (Sync, Delete, Set Threshold MB/SMS, Enable/Disable Notify by Email/SMS).

### 2. Setting Up WooCommerce Renewal Products

1. Go to **Products > Add New** (or edit an existing product).
2. Under the product settings, enable the option requiring a 1NCE SIM ICCID.
3. When customers purchase this product, they will be prompted to enter and verify their ICCID before adding it to the cart.
4. When the order reaches the **Completed / Paid** status, the plugin automatically triggers the 1NCE top-up API and logs details to the order notes.

### 3. Frontend SIM Status Lookup Shortcode

Place the shortcode on any page, post, or widget:

```text
[1nce]
```

This renders a search interface where customers can look up their ICCID to check remaining data and SMS allowances.

---

## License

This plugin is open-source software licensed under the [GNU General Public License v2.0 or later](LICENSE).

## Author

- **Keramaros Antonios** - [https://keramaros.gr](https://keramaros.gr)
