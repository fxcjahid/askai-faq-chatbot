=== AskAI FAQ Chatbot ===
Contributors: fxcjahid
Tags: chatbot, ai, faq, openai, claude
Requires at least: 5.5
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI FAQ chatbot for WordPress. Supports Claude, OpenAI, and Gemini. Replies only from your FAQ in the user's own language.

== Description ==

**AskAI FAQ Chatbot** lets you add an AI chat assistant to your WordPress site that answers visitor questions **only from the FAQ data you provide**. It will politely decline anything outside that scope, so it won't make things up or wander off-topic.

You stay in control: paste your own API key, write your own instructions and Q&A, and pick the AI provider you trust.

= Key features =

* **3 AI providers** — Anthropic Claude, OpenAI GPT, or Google Gemini. Pick whichever has the best price/quality for you.
* **Strictly your FAQ** — the bot refuses to answer anything not covered by your data, and suggests related questions from your list.
* **Auto-detects user language** — visitor writes in Bangla, the bot replies in Bangla. Same for Hindi, Arabic, Spanish, French, etc.
* **Two display modes** — floating chat bubble on every page, or `[faq_chatbot]` shortcode for inline embedding.
* **Fully configurable from admin** — provider, model, API key, instructions, FAQ data, colors, position, welcome message, quick replies.
* **Markdown rendering** — bold, italic, lists, links in bot replies.
* **Quick reply chips** — show starter questions when chat opens.
* **Rate limiting** — protect your API budget from spam.
* **Test Connection button** — verify everything works before going live.
* **No data leaves your server unless asked** — only the user's message + your FAQ goes to the AI provider you chose. The API key never leaves your WordPress server.

= Use cases =

* Customer support FAQ for e-commerce sites
* Documentation assistant for SaaS / membership sites
* Course Q&A bot for LMS sites
* Service business FAQ (clinics, agencies, restaurants)

= Third-party services =

This plugin sends user messages, your FAQ data, and your instructions to **one** AI provider that **you** choose in the admin settings:

* **Anthropic Claude** — https://www.anthropic.com/ — [Terms](https://www.anthropic.com/legal/consumer-terms) | [Privacy](https://www.anthropic.com/legal/privacy)
* **OpenAI GPT** — https://openai.com/ — [Terms](https://openai.com/policies/terms-of-use) | [Privacy](https://openai.com/policies/privacy-policy)
* **Google Gemini** — https://ai.google.dev/ — [Terms](https://policies.google.com/terms) | [Privacy](https://policies.google.com/privacy)

No data is sent until an admin configures an API key and a visitor sends a chat message. Read your chosen provider's terms to understand how they handle data.

== Installation ==

1. Upload the `askai-faq-chatbot` folder to `/wp-content/plugins/` — or install from the Plugins screen in WordPress admin.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **FAQ Chatbot** in the WordPress admin sidebar.
4. Choose your AI provider (Anthropic / OpenAI / Gemini) and paste your API key.
5. Add your FAQ data in the "FAQ Entries" textarea using `Q:` / `A:` format.
6. Click **Save All Settings**, then click **Send Test Message** to verify it works.
7. The floating chat bubble appears on every page by default, or use `[faq_chatbot]` in any page/post.

== Frequently Asked Questions ==

= Do I need an API key? =

Yes. You need an account with at least one of: Anthropic, OpenAI, or Google AI Studio. Google Gemini has a generous free tier, perfect for testing.

= Will the bot answer questions outside my FAQ? =

No — that's the point. The instruction prompt strictly tells the AI to only use the FAQ you provide and to refuse anything else with a polite suggestion of related FAQ topics.

= Can I switch AI providers later? =

Yes. The plugin stores a separate API key for each provider. Switch providers anytime in the admin — no code changes, no data loss.

= Does the bot work in languages other than English? =

Yes. The default instruction tells the AI to detect the user's language and reply in the same language, while translating your FAQ on the fly. Tested with English, Bangla, Hindi, Arabic, Spanish, French.

= Is my API key safe? =

The API key is stored as a WordPress option in your database and is **never exposed to site visitors**. All API requests are made server-side from your WordPress install.

= How do I add the chat to a specific page only? =

Disable the "Floating Widget" toggle, then use the shortcode `[faq_chatbot]` on the page you want.

= Can I customize the look? =

Yes — primary color, chat title, welcome message, and floating widget position are configurable from the admin. Per-page overrides via shortcode attributes: `[faq_chatbot title="Help" color="#22c55e"]`.

= How do I prevent abuse of my API budget? =

Set a rate limit in **Generation Settings**. Default is 10 messages per IP per minute. Set to 0 to disable.

== Screenshots ==

1. Admin settings — provider selection and API keys
2. Admin settings — instructions and FAQ data
3. Floating chat widget on the frontend
4. Inline chatbot via shortcode
5. Quick reply suggestions and markdown rendering

== Changelog ==

= 1.3.0 =
* Added Test Connection button in admin
* Added Quick Reply suggestions
* Added Markdown rendering in bot replies
* Added shortcode attributes (`title`, `welcome`, `color`)
* Added floating widget position setting (4 corners)
* Added animated typing dots
* Added rate limiting (per-IP, transient-based)
* `{COMPANY_NAME}` placeholder now works in chat title and welcome message

= 1.2.0 =
* Added OpenAI GPT support
* Added Google Gemini support
* Separate API key storage per provider
* Removed default FAQ data — admin must configure
* Renamed plugin to AskAI FAQ Chatbot

= 1.1.0 =
* Split instruction prompt and FAQ data into separate fields
* Added `{COMPANY_NAME}` placeholder
* Added temperature, max tokens, conversation memory controls
* Added primary color picker
* Added Company Name field

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.3.0 =
New features: Test Connection, Quick Replies, Markdown rendering, Shortcode attributes, Widget position, Animated typing, Rate limiting. Fully backwards compatible.

= 1.2.0 =
Adds OpenAI and Gemini support. Your existing Anthropic API key is auto-migrated.
