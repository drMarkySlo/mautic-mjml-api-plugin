# Mautic MJML API Bundle

A Mautic plugin that enables the creation and modification of emails via API using **MJML** content. 

This plugin solves a common problem when automating email creation in Mautic: it allows you to upload MJML code, automatically compiles it to HTML for sending, and stores the original MJML in the **GrapesJS builder** tables. This ensures that emails created via API remain fully editable within Mautic's visual builder.

## 🚀 Features

- **Create Emails via API**: Send MJML code to a custom endpoint and get a fully configured Mautic email entity.
- **Update Emails via API**: Patch existing emails with new MJML content, subjects, or segment lists.
- **Automatic Compilation**: The plugin automatically compiles MJML to HTML using the system's MJML binary or Node.js.
- **GrapesJS Integration**: Unlike standard API uploads which only save HTML, this plugin saves the MJML source to the GrapesJS builder, allowing for future visual editing.
- **Segment/List Management**: Easily assign or update email segments during creation or editing.

## 📋 Requirements

- **Mautic**: Version 4.x or 5.x
- **Mautic GrapesJS Builder Bundle**: This is the standard builder in recent Mautic versions.
- **MJML** (Recommended):
  - For the best results, `mjml` should be installed globally on your server via Node.js.
  - Command: `npm install -g mjml`
  - *Note: The plugin contains a fallback mechanism to try compiling via a temporary Node script if the global binary is not found, but a global installation is preferred for performance.*

## 📦 Installation

### Manual Installation

1. **Download**: Download the plugin files.
2. **Upload**: Upload the `MauticMjmlApiBundle` folder to your Mautic plugins directory:
   ```
   /path/to/mautic/plugins/MauticMjmlApiBundle
   ```
3. **Clear Cache**: Run the Mautic console command to clear the cache:
   ```bash
   php bin/console cache:clear
   ```
4. **Install**:
   - Go to your Mautic Admin Dashboard.
   - Navigate to **Settings** (gear icon) -> **Plugins**.
   - Click the **Install/Upgrade Plugins** button.
   - The "MJML API Bundle" should appear in the list.

## 🔌 API Documentation

All endpoints require standard Mautic API Authentication (Basic Auth or OAuth).

### 1. Create New Email

Create a new email by sending MJML content.

- **Endpoint**: `POST /api/emails/mjml/new`
- **Content-Type**: `application/json`

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|:--------:|-------------|
| `name` | string | **Yes** | Internal name of the email (visible in Mautic UI). |
| `subject` | string | **Yes** | The email subject line. |
| `mjml` | string | **Yes** | The raw MJML source code. |
| `lists` | array | **Yes** | Array of Segment IDs (e.g., `[1, 5]`). |
| `isPublished` | boolean | No | `true` or `false` (default: `false`). |
| `publishUp` | string | No | Publish start date/time (ISO 8601 format, e.g., `2025-12-15T10:00:00`). |
| `publishDown` | string | No | Publish end date/time (ISO 8601 format, e.g., `2025-12-31T23:59:59`). |
| `utmTags` | object | No | UTM tracking parameters (e.g., `{"utmSource": "newsletter", "utmMedium": "email"}`). |
| `template` | string | No | Mautic theme/template. Default is `blank`. **Recommended to keep generic/blank** to avoid style conflicts. |
| `fromAddress` | string | No | Sender email address. |
| `fromName` | string | No | Sender name. |
| `replyToAddress` | string | No | Reply-to email address. |
| `language` | string | No | Language code (e.g., `en`, `sl`). Default: `en`. |

#### Example Request (cURL)

```bash
curl -X POST 'https://your-mautic-url.com/api/emails/mjml/new' \
  -u 'username:password' \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "November Newsletter 2025",
    "subject": "Weekly Highlights",
    "mjml": "<mjml><mj-body><mj-section><mj-column><mj-text>Hello World!</mj-text></mj-column></mj-section></mj-body></mjml>",
    "lists": [3, 15],
    "fromAddress": "news@example.com",
    "fromName": "Company News",
    "isPublished": false,
    "publishUp": "2025-12-15T10:00:00",
    "publishDown": "2025-12-31T23:59:59",
    "utmTags": {
      "utmSource": "newsletter",
      "utmMedium": "email",
      "utmCampaign": "december2025"
    }
  }'
```

#### Success Response (201 Created)

```json
{
  "email": {
    "id": 150,
    "name": "November Newsletter 2025",
    "subject": "Weekly Highlights",
    "isPublished": false,
    "dateAdded": "2025-12-11T10:00:00+00:00",
    "lists": [
      { "id": 3, "name": "Newsletter" },
      { "id": 15, "name": "Customers" }
    ]
  }
}
```

---

### 2. Update Existing Email

Update an existing email's MJML content, subject, or settings.

- **Endpoint**: `PATCH /api/emails/mjml/{id}/edit`
- **Content-Type**: `application/json`

#### Behavior
- Updates only the fields provided in the request.
- **Warning regarding Lists**: If you provide the `lists` parameter, it will **overwrite** all existing segments assigned to the email. To add a segment, you must send the existing IDs plus the new one.

#### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `mjml` | string | New MJML source code. Will be re-compiled to HTML. |
| `subject` | string | New subject line. |
| `name` | string | New internal name. |
| `isPublished` | boolean | Change publication status. |
| `publishUp` | string | Publish start date/time (ISO 8601 format). Set to empty string to clear. |
| `publishDown` | string | Publish end date/time (ISO 8601 format). Set to empty string to clear. |
| `utmTags` | object | UTM tracking parameters (overwrites existing). |
| `fromAddress` | string | Sender email address. |
| `fromName` | string | Sender name. |
| `replyToAddress` | string | Reply-to email address. |
| `lists` | array | New array of segment IDs (overwrites existing!). |

#### Example Request

```bash
curl -X PATCH 'https://your-mautic-url.com/api/emails/mjml/150/edit' \
  -u 'username:password' \
  -H 'Content-Type: application/json' \
  -d '{
    "mjml": "<mjml><mj-body><mj-section><mj-column><mj-text>Updated Content!</mj-text></mj-column></mj-section></mj-body></mjml>",
    "subject": "Updated Subject Line"
  }'
```

## ⚙️ How It Works

1. **Request**: The API receives the JSON payload containing the MJML string.
2. **Compilation**: The `MjmlCompiler` helper class processes the MJML:
   - It first checks for the `mjml` CLI command using `exec()`.
   - If CLI is unavailable, it attempts to run a temporary Node.js script.
   - If compilation fails, it logs the error and returns the original content (fallback).
3. **Entity Creation**: A new Mautic `Email` entity is created (or updated) with the compiled HTML set as `customHtml`.
4. **Builder Storage**: The original MJML is saved into the `grapesjs_builder` table. This is the crucial step that allows you to open the email in Mautic's builder later and see the visual blocks instead of raw HTML.

## 🛠️ Troubleshooting

### API returns 404
- Ensure the plugin is installed and listed in "Plugins".
- Clear the cache: `php bin/console cache:clear`.
- Verify your URL structure.

### MJML is not compiling (Raw tags in email)
- Check if `exec()` is enabled in your PHP configuration (`php.ini`).
- Verify that `mjml` is installed on the server: `mjml --version`.
- Check Mautic logs: `var/logs/mautic_prod.php`.

### Segments are disappearing after update
- Remember that the `lists` parameter in the PATCH endpoint is destructive (it replaces the current list set). Include *all* desired list IDs in your update request.

## 📄 License

This project is licensed under the **GPL-3.0** License.

## 👤 Author

**Mark Poljanšek**
- GitHub: [@drMarkySlo](https://github.com/drMarkySlo)