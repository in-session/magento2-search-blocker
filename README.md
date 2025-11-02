# 🛡️ InSession_SearchBlocker for Magento 2

**InSession_SearchBlocker** is a lightweight Magento 2 security and hygiene module that prevents the use of **forbidden, suspicious, or empty search terms** across the Magento storefront, REST API, and GraphQL layer.  
It helps protect your store from spammy searches, SQL-like injection attempts, and unnecessary search load.

---

## 🚀 Features

- ✅ **Global enable/disable switch**
- 🔒 **Search term filtering** based on:
  - Blacklist of forbidden terms (configurable via Admin)
  - Optional regex-based SQL injection pattern detection
- 🌐 **Multi-channel protection**
  - Frontend (catalogsearch/result)
  - REST API (`/rest/V1/products`)
  - GraphQL API (`products(search: "...")`)
- 🔁 **Safe redirect** for blocked frontend searches
- 🧾 **Custom logging**
  - Logs all blocked attempts to `var/log/search_blocker.log`
  - Selectively enable logging for individual channels (Frontend, REST, GraphQL)
- 🧱 Built with **PSR-3 + Monolog**, fully compatible with Magento 2.4.8+

---

## ⚙️ Configuration (Admin)

**Path:**  
`Stores → Configuration → Catalog → Search Blocker`

| Setting | Description |
|----------|--------------|
| **Enable Global Search Blocker** | Master switch for the entire module |
| **Enable for Frontend Search** | Blocks suspicious/blacklisted terms in the storefront search |
| **Enable for REST API** | Filters search terms in REST API requests |
| **Enable for GraphQL** | Filters search terms in GraphQL queries |
| **Blacklisted Search Terms** | Comma-separated list of forbidden keywords |
| **Redirect Path** | URL to redirect blocked users to (e.g. `/`, `/no-search`) |
| **Enable Regex Security Filter** | Activates pattern-based protection (detects SQL-like keywords) |
| **Enable Logging** | Enables file-based logging for blocked attempts |
| **Log Channels** | Choose which channels to log (Frontend, REST, GraphQL) |

---

## 🧩 Technical Overview

| Component | Description |
|------------|-------------|
| `Plugin/PreventSearchOnController.php` | Intercepts Magento’s frontend search controller |
| `Plugin/PreventSearchOnRestApi.php` | Validates REST API search criteria |
| `Plugin/PreventSearchInGraphQl.php` | Validates GraphQL `search` argument |
| `Logger/Handler.php` | Defines log file and level (`var/log/search_blocker.log`) |
| `Logger/Logger.php` | Custom Monolog logger |
| `Model/Config.php` | Central configuration logic and XML path constants |
| `Model/Config/Source/LogChannels.php` | Admin multiselect source for log channels |
| `etc/adminhtml/system.xml` | Admin configuration UI |
| `etc/config.xml` | Default configuration values |

---

## 🧠 Example Log Entry

```bash
[2025-11-02 10:14:25] search_blocker.INFO: Blocked term detected in Frontend Search: "union select" {"channel":"controller"}
```

---

## 🧰 Installation

### Option 1: Composer (recommended)

```bash
composer require insession/magento2-search-blocker
bin/magento module:enable InSession_SearchBlocker
bin/magento setup:upgrade
bin/magento cache:flush
```

### Option 2: Manual Installation

1. Copy the module to:  
   `app/code/InSession/SearchBlocker`
2. Run setup commands:
   ```bash
   bin/magento module:enable InSession_SearchBlocker
   bin/magento setup:upgrade
   bin/magento cache:flush
   ```

---

## 🧩 Compatibility

- Magento **>2.4.5**
- PHP **>8.1**
- Fully compatible with **Hyvä Themes**

---

## 🧑‍💻 Developer Notes

- Uses **around plugins** to validate search parameters *before* Magento’s core logic executes.
- Throws localized exceptions for frontend or API-safe errors.
- Logging handled via Magento’s **Monolog/PSR-3 system** for consistent, configurable output.
- Designed for **CSP-safe environments** — no inline scripts, no JS dependencies.

---

🛡️ *"Better searches, fewer threats."*
