# SuperGrok OAuth

Nextcloud SuperGrok OAuth. Conversation, Voice, Imagine. Sign in with SuperGrok / X Premium+. No API key. SpaceXAI.

## What you get

- **Conversation:** Nextcloud Assistant free prompt and chat (Task Processing `TextToText` / `TextToTextChat`)
- **Voice:** speech-to-text and text-to-speech (`AudioToText` / `TextToSpeech`)
- **Imagine:** image generation (`TextToImage`)

Assistant buttons (summary, headline, topics, reformulation, change tone, proofread, context write, emoji, translate) use the same SuperGrok session.

Talk AI and other Task Processing consumers see the same providers.

## Install

The app folder name **must** be `supergrok`. The GitHub repo is `nc-supergrok`.

1. Clone or download into `nextcloud/apps/supergrok`:

   ```bash
   cd /path/to/nextcloud/apps
   git clone https://github.com/helv-io/nc-supergrok.git supergrok
   ```

2. Enable the app:

   ```bash
   sudo -u www-data php occ app:enable supergrok
   ```

3. Open **Personal settings → Artificial intelligence → SuperGrok OAuth** and sign in.

## Sign in

Sign in with SuperGrok or X Premium+. There is no API key field.

**Device code is the default.** Click Sign in with device code, open the verification URL on any device, enter the user code if asked, and approve. Nextcloud polls until you approve or the code expires.

**Browser login is the backup.** Click Open SuperGrok. After you approve, the browser opens `http://127.0.0.1:56121/callback` and says the site cannot be reached. That is expected: it is the only redirect registered on the public Grok CLI client. Copy the full URL from the address bar (or the query string, or the bare code) and paste it into the form.

This app does not bind `127.0.0.1:56121` on the Nextcloud server.

## How it maps

| SuperGrok | Nextcloud |
| --- | --- |
| Conversation | Assistant text / chat (Task Processing) |
| Imagine | Assistant image generation |
| Voice STT / TTS | Talk AI and Assistant speech |
| Realtime | Withheld in this release |

Admins can set default models and turn providers on or off under **Administration settings → Artificial intelligence**. Tokens stay per-user. An instance-wide session (empty user id) is only used as a fallback if one was stored.

## Requirements

- Nextcloud 32, 33, or 34
- PHP 8.1 to 8.4
- SuperGrok or X Premium+

## Troubleshooting

**`redirect_uri does not match any registered URI`**
You used a different callback. Use device code (the default), or browser login and paste `http://127.0.0.1:56121/callback?...`.

**Browser says the site cannot be reached after sign-in**
Expected. Copy `http://127.0.0.1:56121/callback?code=...` from the address bar and paste it back.

**Assistant says sign in**
Open Personal settings and complete SuperGrok OAuth. Providers do not use a shared admin API key.

## License

[AGPL-3.0-or-later](LICENSE)

OAuth grant types and API constants match [helv-io/ha-supergrok](https://github.com/helv-io/ha-supergrok) (MIT).
