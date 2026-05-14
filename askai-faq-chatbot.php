<?php
/**
 * Plugin Name: AskAI FAQ Chatbot
 * Description: AI-powered FAQ chatbot supporting Anthropic Claude, OpenAI GPT, and Google Gemini. Replies only from your configured FAQ data and answers in the user's own language.
 * Version:     1.3.0
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Author:      Fxc Jahid
 * Author URI:  https://profiles.wordpress.org/fxcjahid/
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: askai-faq-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NFC_VERSION', '1.3.0' );
define( 'NFC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NFC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ============================================================
// Providers & their models
// ============================================================
function nfc_providers() {
    return [
        'anthropic' => [
            'label'  => 'Anthropic Claude',
            'models' => [
                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — fast & cheap (recommended)',
                'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 — balanced',
                'claude-opus-4-7'           => 'Claude Opus 4.7 — most capable',
            ],
        ],
        'openai' => [
            'label'  => 'OpenAI GPT',
            'models' => [
                'gpt-4o-mini' => 'GPT-4o mini — fast & cheap (recommended)',
                'gpt-4o'      => 'GPT-4o — balanced',
                'gpt-4-turbo' => 'GPT-4 Turbo — most capable',
            ],
        ],
        'gemini' => [
            'label'  => 'Google Gemini',
            'models' => [
                'gemini-2.0-flash' => 'Gemini 2.0 Flash — fast (recommended)',
                'gemini-1.5-flash' => 'Gemini 1.5 Flash — cheap',
                'gemini-1.5-pro'   => 'Gemini 1.5 Pro — most capable',
            ],
        ],
    ];
}

// ============================================================
// Default instruction prompt (FAQ data has NO default — set by admin)
// ============================================================
function nfc_default_instructions() {
    return "You are a customer support FAQ chatbot for \"{COMPANY_NAME}\".

STRICT RULES:
1. ONLY answer questions using the FAQ DATA provided below.
2. If the user's question is NOT covered by the FAQ data, do NOT make up an answer.
   Instead, reply with the equivalent of: \"I'm sorry, I can only help with questions about our FAQ topics.\"
   Then suggest 2-3 closest FAQ questions from the list that might be related.
3. ALWAYS reply in the SAME language the user wrote their message in.
   - If the user writes in English, reply in English.
   - If the user writes in Bengali (Bangla), reply in Bengali.
   - If the user writes in Hindi, Arabic, Spanish, French, or any other language, reply in that same language.
   - Translate the FAQ answers into the user's language naturally — keep the meaning identical.
   - Proper nouns (company name, email addresses, phone numbers, URLs) stay unchanged.
   - If the user mixes languages, follow the dominant language of their last message.
4. Keep replies short, friendly, and professional with an occasional light/funny tone.
5. Never discuss topics unrelated to this FAQ (no general knowledge, news, jokes, coding help, etc.).
6. If the user asks who you are, say you are the {COMPANY_NAME} FAQ assistant (in their language).";
}

function nfc_defaults() {
    return [
        'nfc_provider'           => 'anthropic',
        'nfc_api_key_anthropic'  => '',
        'nfc_api_key_openai'     => '',
        'nfc_api_key_gemini'     => '',
        'nfc_model'              => 'claude-haiku-4-5-20251001',
        'nfc_max_tokens'         => 512,
        'nfc_temperature'        => 0.7,
        'nfc_history_limit'      => 20,
        'nfc_company_name'       => 'Nexsoflex',
        'nfc_instructions'       => nfc_default_instructions(),
        'nfc_faq_data'           => '',
        'nfc_chat_title'         => 'FAQ Assistant',
        'nfc_welcome_message'    => 'Hi! I can answer questions from our FAQ. How can I help you today?',
        'nfc_primary_color'      => '#667eea',
        'nfc_widget_enabled'     => '1',
        'nfc_widget_position'    => 'bottom-right',
        'nfc_quick_replies'      => '',
        'nfc_rate_limit'         => 10,
    ];
}

// ============================================================
// Activation — seed defaults + migrate legacy key
// ============================================================
register_activation_hook( __FILE__, 'nfc_activate' );
function nfc_activate() {
    foreach ( nfc_defaults() as $key => $value ) {
        if ( false === get_option( $key ) ) {
            update_option( $key, $value );
        }
    }
    // Migrate legacy single API key (v1.0–1.1) to anthropic slot
    $legacy = get_option( 'nfc_api_key' );
    if ( $legacy && ! get_option( 'nfc_api_key_anthropic' ) ) {
        update_option( 'nfc_api_key_anthropic', $legacy );
        delete_option( 'nfc_api_key' );
    }
}

// ============================================================
// Admin menu + settings page
// ============================================================
add_action( 'admin_menu', 'nfc_admin_menu' );
function nfc_admin_menu() {
    add_menu_page(
        'FAQ Chatbot',
        'FAQ Chatbot',
        'manage_options',
        'nfc-settings',
        'nfc_render_settings_page',
        'dashicons-format-chat',
        80
    );
}

add_action( 'admin_init', 'nfc_register_settings' );
function nfc_register_settings() {
    register_setting( 'nfc_settings_group', 'nfc_provider',          [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_api_key_anthropic', [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_api_key_openai',    [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_api_key_gemini',    [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_model',             [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_max_tokens',        [ 'sanitize_callback' => 'absint' ] );
    register_setting( 'nfc_settings_group', 'nfc_temperature',       [ 'sanitize_callback' => 'nfc_sanitize_float' ] );
    register_setting( 'nfc_settings_group', 'nfc_history_limit',     [ 'sanitize_callback' => 'absint' ] );
    register_setting( 'nfc_settings_group', 'nfc_company_name',      [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_instructions',      [ 'sanitize_callback' => 'wp_unslash' ] );
    register_setting( 'nfc_settings_group', 'nfc_faq_data',          [ 'sanitize_callback' => 'wp_unslash' ] );
    register_setting( 'nfc_settings_group', 'nfc_chat_title',        [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_welcome_message',   [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_primary_color',     [ 'sanitize_callback' => 'sanitize_hex_color' ] );
    register_setting( 'nfc_settings_group', 'nfc_widget_enabled',    [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_widget_position',   [ 'sanitize_callback' => 'sanitize_text_field' ] );
    register_setting( 'nfc_settings_group', 'nfc_quick_replies',     [ 'sanitize_callback' => 'wp_unslash' ] );
    register_setting( 'nfc_settings_group', 'nfc_rate_limit',        [ 'sanitize_callback' => 'absint' ] );
}

function nfc_sanitize_float( $value ) {
    $value = floatval( $value );
    if ( $value < 0 ) { return 0; }
    if ( $value > 2 ) { return 2; }
    return $value;
}

function nfc_get( $key ) {
    $defaults = nfc_defaults();
    $default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
    $value    = get_option( $key, $default );
    return ( null === $value ) ? $default : $value;
}

function nfc_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $providers       = nfc_providers();
    $provider        = nfc_get( 'nfc_provider' );
    $key_anthropic   = nfc_get( 'nfc_api_key_anthropic' );
    $key_openai      = nfc_get( 'nfc_api_key_openai' );
    $key_gemini      = nfc_get( 'nfc_api_key_gemini' );
    $model           = nfc_get( 'nfc_model' );
    $max_tokens      = nfc_get( 'nfc_max_tokens' );
    $temperature     = nfc_get( 'nfc_temperature' );
    $history_limit   = nfc_get( 'nfc_history_limit' );
    $company_name    = nfc_get( 'nfc_company_name' );
    $instructions    = nfc_get( 'nfc_instructions' );
    $faq_data        = nfc_get( 'nfc_faq_data' );
    $chat_title      = nfc_get( 'nfc_chat_title' );
    $welcome_message = nfc_get( 'nfc_welcome_message' );
    $primary_color   = nfc_get( 'nfc_primary_color' );
    $widget_enabled  = nfc_get( 'nfc_widget_enabled' );
    $widget_position = nfc_get( 'nfc_widget_position' );
    $quick_replies   = nfc_get( 'nfc_quick_replies' );
    $rate_limit      = nfc_get( 'nfc_rate_limit' );
    ?>
    <div class="wrap nfc-admin">
        <h1>AskAI FAQ Chatbot</h1>
        <p>Configure your AI-powered FAQ chatbot. API keys are stored on the server and never exposed to site visitors.</p>

        <?php if ( empty( $faq_data ) ) : ?>
            <div class="notice notice-warning"><p><strong>FAQ data is empty.</strong> Add your Q&amp;A entries below before the chatbot can answer anything.</p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'nfc_settings_group' ); ?>

            <h2 class="title">AI Provider</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nfc_provider">Provider</label></th>
                    <td>
                        <select id="nfc_provider" name="nfc_provider">
                            <?php foreach ( $providers as $slug => $p ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $provider, $slug ); ?>><?php echo esc_html( $p['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Choose which AI service to power the chatbot.</p>
                    </td>
                </tr>
                <tr class="nfc-row-anthropic">
                    <th scope="row"><label for="nfc_api_key_anthropic">Anthropic API Key</label></th>
                    <td>
                        <input type="password" id="nfc_api_key_anthropic" name="nfc_api_key_anthropic" value="<?php echo esc_attr( $key_anthropic ); ?>" class="regular-text" autocomplete="off" />
                        <button type="button" class="button" onclick="nfcToggle('nfc_api_key_anthropic')">Show/Hide</button>
                        <p class="description">Get one at <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a></p>
                    </td>
                </tr>
                <tr class="nfc-row-openai">
                    <th scope="row"><label for="nfc_api_key_openai">OpenAI API Key</label></th>
                    <td>
                        <input type="password" id="nfc_api_key_openai" name="nfc_api_key_openai" value="<?php echo esc_attr( $key_openai ); ?>" class="regular-text" autocomplete="off" />
                        <button type="button" class="button" onclick="nfcToggle('nfc_api_key_openai')">Show/Hide</button>
                        <p class="description">Get one at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a></p>
                    </td>
                </tr>
                <tr class="nfc-row-gemini">
                    <th scope="row"><label for="nfc_api_key_gemini">Google Gemini API Key</label></th>
                    <td>
                        <input type="password" id="nfc_api_key_gemini" name="nfc_api_key_gemini" value="<?php echo esc_attr( $key_gemini ); ?>" class="regular-text" autocomplete="off" />
                        <button type="button" class="button" onclick="nfcToggle('nfc_api_key_gemini')">Show/Hide</button>
                        <p class="description">Get one at <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com/app/apikey</a> (generous free tier)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_model">Model</label></th>
                    <td>
                        <select id="nfc_model" name="nfc_model" data-current="<?php echo esc_attr( $model ); ?>">
                            <?php
                            foreach ( $providers as $slug => $p ) {
                                foreach ( $p['models'] as $m_id => $m_label ) {
                                    printf(
                                        '<option value="%s" data-provider="%s" %s>%s</option>',
                                        esc_attr( $m_id ),
                                        esc_attr( $slug ),
                                        selected( $model, $m_id, false ),
                                        esc_html( $m_label )
                                    );
                                }
                            }
                            ?>
                        </select>
                        <p class="description">Models update automatically when you change the provider above.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Test Connection</th>
                    <td>
                        <button type="button" id="nfc-test-btn" class="button button-secondary">Send Test Message</button>
                        <span id="nfc-test-result" style="margin-left:12px;"></span>
                        <p class="description">Verify the selected provider, model, and API key work. <strong>Save settings first</strong> before testing.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Generation Settings</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nfc_max_tokens">Max Response Length</label></th>
                    <td>
                        <input type="number" id="nfc_max_tokens" name="nfc_max_tokens" value="<?php echo esc_attr( $max_tokens ); ?>" min="64" max="4096" step="1" />
                        <p class="description">Maximum tokens per reply. 512 ≈ 350 words.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_temperature">Temperature</label></th>
                    <td>
                        <input type="number" id="nfc_temperature" name="nfc_temperature" value="<?php echo esc_attr( $temperature ); ?>" min="0" max="2" step="0.1" />
                        <p class="description">0 = deterministic. 1 = creative. 0.7 is balanced. (Anthropic/Gemini cap at 1, OpenAI allows up to 2.)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_history_limit">Conversation Memory</label></th>
                    <td>
                        <input type="number" id="nfc_history_limit" name="nfc_history_limit" value="<?php echo esc_attr( $history_limit ); ?>" min="2" max="50" step="2" />
                        <p class="description">Number of recent messages remembered for context.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_rate_limit">Rate Limit</label></th>
                    <td>
                        <input type="number" id="nfc_rate_limit" name="nfc_rate_limit" value="<?php echo esc_attr( $rate_limit ); ?>" min="0" max="100" step="1" />
                        <p class="description">Max messages per IP per minute. Set to <code>0</code> to disable. Protects your API budget from spam.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Appearance</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nfc_chat_title">Chat Title</label></th>
                    <td>
                        <input type="text" id="nfc_chat_title" name="nfc_chat_title" value="<?php echo esc_attr( $chat_title ); ?>" class="regular-text" />
                        <p class="description">Shown in the chat header. You can use <code>{COMPANY_NAME}</code> as a placeholder.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_welcome_message">Welcome Message</label></th>
                    <td>
                        <input type="text" id="nfc_welcome_message" name="nfc_welcome_message" value="<?php echo esc_attr( $welcome_message ); ?>" class="regular-text" />
                        <p class="description">First message shown to visitors. You can use <code>{COMPANY_NAME}</code> as a placeholder.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_primary_color">Primary Color</label></th>
                    <td>
                        <input type="color" id="nfc_primary_color" name="nfc_primary_color" value="<?php echo esc_attr( $primary_color ); ?>" />
                        <p class="description">Header, send button, and user message bubble color.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_widget_enabled">Floating Widget</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="nfc_widget_enabled" name="nfc_widget_enabled" value="1" <?php checked( $widget_enabled, '1' ); ?> />
                            Show floating chat bubble on every page
                        </label>
                        <p class="description">Disable this if you only want to use the <code>[faq_chatbot]</code> shortcode.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_widget_position">Widget Position</label></th>
                    <td>
                        <select id="nfc_widget_position" name="nfc_widget_position">
                            <option value="bottom-right" <?php selected( $widget_position, 'bottom-right' ); ?>>Bottom Right (default)</option>
                            <option value="bottom-left"  <?php selected( $widget_position, 'bottom-left' );  ?>>Bottom Left</option>
                            <option value="top-right"    <?php selected( $widget_position, 'top-right' );    ?>>Top Right</option>
                            <option value="top-left"     <?php selected( $widget_position, 'top-left' );     ?>>Top Left</option>
                        </select>
                        <p class="description">Where the floating chat bubble appears on the page.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_quick_replies">Quick Reply Suggestions</label></th>
                    <td>
                        <textarea id="nfc_quick_replies" name="nfc_quick_replies" rows="4" class="large-text" placeholder="What are your business hours?&#10;How do I track my order?&#10;What is your refund policy?"><?php echo esc_textarea( $quick_replies ); ?></textarea>
                        <p class="description">One question per line. Shown as clickable chips when chat opens. Leave empty to hide. Max 4 suggestions.</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Instruction Prompt (System)</h2>
            <p>How the AI should behave. Use <code>{COMPANY_NAME}</code> as a placeholder — it will be replaced with the Company Name below.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nfc_company_name">Company Name</label></th>
                    <td><input type="text" id="nfc_company_name" name="nfc_company_name" value="<?php echo esc_attr( $company_name ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="nfc_instructions">Instructions</label></th>
                    <td>
                        <textarea id="nfc_instructions" name="nfc_instructions" rows="14" class="large-text code"><?php echo esc_textarea( $instructions ); ?></textarea>
                        <p class="description">Rules and tone for the chatbot. The FAQ data below is appended automatically.</p>
                        <button type="button" class="button" onclick="if(confirm('Reset instructions to default?')){document.getElementById('nfc_instructions').value=<?php echo wp_json_encode( nfc_default_instructions() ); ?>;}">Reset to default</button>
                    </td>
                </tr>
            </table>

            <h2 class="title">FAQ Data (Q &amp; A)</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nfc_faq_data">FAQ Entries</label></th>
                    <td>
                        <textarea id="nfc_faq_data" name="nfc_faq_data" rows="20" class="large-text code" placeholder="Q: What are your business hours?&#10;A: We are open Monday to Friday, 9 AM to 6 PM.&#10;&#10;Q: How can I contact support?&#10;A: Email us at support@example.com&#10;&#10;..."><?php echo esc_textarea( $faq_data ); ?></textarea>
                        <p class="description">Format: <code>Q:</code> question on one line, <code>A:</code> answer on the next line, blank line between entries. <strong>Required</strong> — the chatbot will refuse to answer without FAQ data.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Save All Settings' ); ?>
        </form>

        <h2>How to display the chatbot</h2>
        <ol>
            <li><strong>Floating widget:</strong> enable above to show a chat bubble on every page.</li>
            <li><strong>Shortcode:</strong> add <code>[faq_chatbot]</code> to any page or post.</li>
            <li><strong>PHP template:</strong> use <code>&lt;?php echo do_shortcode('[faq_chatbot]'); ?&gt;</code> in any theme file.</li>
        </ol>

        <script>
        function nfcToggle(id){var f=document.getElementById(id);f.type=f.type==='password'?'text':'password';}
        (function(){
            var providerEl = document.getElementById('nfc_provider');
            var modelEl    = document.getElementById('nfc_model');
            var rows = {
                anthropic: document.querySelector('.nfc-row-anthropic'),
                openai:    document.querySelector('.nfc-row-openai'),
                gemini:    document.querySelector('.nfc-row-gemini')
            };
            function refresh(){
                var p = providerEl.value;
                Object.keys(rows).forEach(function(k){ rows[k].style.display = (k===p) ? '' : 'none'; });
                var current = modelEl.value;
                var firstMatch = null;
                Array.prototype.forEach.call(modelEl.options, function(opt){
                    var match = opt.getAttribute('data-provider') === p;
                    opt.hidden = !match;
                    opt.disabled = !match;
                    if (match && firstMatch === null) firstMatch = opt.value;
                });
                var currentOpt = modelEl.querySelector('option[value="'+current+'"]');
                if (!currentOpt || currentOpt.getAttribute('data-provider') !== p){
                    if (firstMatch) modelEl.value = firstMatch;
                }
            }
            providerEl.addEventListener('change', refresh);
            refresh();
        })();

        // Test Connection
        (function(){
            var btn = document.getElementById('nfc-test-btn');
            var out = document.getElementById('nfc-test-result');
            if (!btn) return;
            btn.addEventListener('click', function(){
                btn.disabled = true;
                out.style.color = '#666';
                out.textContent = 'Testing...';
                var fd = new FormData();
                fd.append('action', 'nfc_test_connection');
                fd.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'nfc_admin_nonce' ) ); ?>');
                fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method:'POST', body: fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.success) {
                            out.style.color = '#16a34a';
                            out.textContent = '✓ Connected! Reply: "' + (d.data.reply || '').slice(0, 80) + (d.data.reply && d.data.reply.length > 80 ? '...' : '') + '"';
                        } else {
                            out.style.color = '#dc2626';
                            out.textContent = '✗ ' + ((d.data && d.data.message) || 'Test failed');
                        }
                    })
                    .catch(function(e){
                        out.style.color = '#dc2626';
                        out.textContent = '✗ ' + e.message;
                    })
                    .finally(function(){ btn.disabled = false; });
            });
        })();
        </script>
    </div>
    <?php
}

// ============================================================
// Build the system prompt (instructions + FAQ data)
// ============================================================
function nfc_build_system_prompt() {
    $instructions = nfc_replace_placeholders( nfc_get( 'nfc_instructions' ) );
    $faq_data     = nfc_get( 'nfc_faq_data' );

    return $instructions . "\n\n==============================================\nFAQ DATA\n==============================================\n\n" . $faq_data;
}

// ============================================================
// Provider dispatch — returns ['reply' => string] or WP_Error
// ============================================================
function nfc_call_provider( $provider, $model, $system, $messages, $max_tokens, $temperature ) {
    switch ( $provider ) {
        case 'openai':
            return nfc_call_openai( $model, $system, $messages, $max_tokens, $temperature );
        case 'gemini':
            return nfc_call_gemini( $model, $system, $messages, $max_tokens, $temperature );
        case 'anthropic':
        default:
            return nfc_call_anthropic( $model, $system, $messages, $max_tokens, $temperature );
    }
}

function nfc_call_anthropic( $model, $system, $messages, $max_tokens, $temperature ) {
    $api_key = nfc_get( 'nfc_api_key_anthropic' );
    if ( empty( $api_key ) ) {
        return new WP_Error( 'no_key', 'Anthropic API key is not set.' );
    }

    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 30,
        'headers' => [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => wp_json_encode( [
            'model'       => $model,
            'max_tokens'  => $max_tokens,
            'temperature' => min( 1, $temperature ),
            'system'      => $system,
            'messages'    => $messages,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) { return $response; }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 200 !== $code ) {
        $err = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Anthropic API error';
        return new WP_Error( 'api_error', $err, [ 'status' => $code ] );
    }

    $reply = isset( $body['content'][0]['text'] ) ? $body['content'][0]['text'] : '(empty response)';
    return [ 'reply' => $reply ];
}

function nfc_call_openai( $model, $system, $messages, $max_tokens, $temperature ) {
    $api_key = nfc_get( 'nfc_api_key_openai' );
    if ( empty( $api_key ) ) {
        return new WP_Error( 'no_key', 'OpenAI API key is not set.' );
    }

    $oa_messages = array_merge(
        [ [ 'role' => 'system', 'content' => $system ] ],
        $messages
    );

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 30,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => wp_json_encode( [
            'model'       => $model,
            'max_tokens'  => $max_tokens,
            'temperature' => $temperature,
            'messages'    => $oa_messages,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) { return $response; }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 200 !== $code ) {
        $err = isset( $body['error']['message'] ) ? $body['error']['message'] : 'OpenAI API error';
        return new WP_Error( 'api_error', $err, [ 'status' => $code ] );
    }

    $reply = isset( $body['choices'][0]['message']['content'] ) ? $body['choices'][0]['message']['content'] : '(empty response)';
    return [ 'reply' => $reply ];
}

function nfc_call_gemini( $model, $system, $messages, $max_tokens, $temperature ) {
    $api_key = nfc_get( 'nfc_api_key_gemini' );
    if ( empty( $api_key ) ) {
        return new WP_Error( 'no_key', 'Google Gemini API key is not set.' );
    }

    // Gemini uses "model" instead of "assistant" and a different content shape
    $contents = [];
    foreach ( $messages as $m ) {
        $contents[] = [
            'role'  => ( 'assistant' === $m['role'] ) ? 'model' : 'user',
            'parts' => [ [ 'text' => $m['content'] ] ],
        ];
    }

    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
        rawurlencode( $model ),
        rawurlencode( $api_key )
    );

    $response = wp_remote_post( $url, [
        'timeout' => 30,
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [
            'systemInstruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
            'contents'          => $contents,
            'generationConfig'  => [
                'maxOutputTokens' => $max_tokens,
                'temperature'     => min( 1, $temperature ),
            ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) { return $response; }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 200 !== $code ) {
        $err = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Gemini API error';
        return new WP_Error( 'api_error', $err, [ 'status' => $code ] );
    }

    $reply = isset( $body['candidates'][0]['content']['parts'][0]['text'] )
        ? $body['candidates'][0]['content']['parts'][0]['text']
        : '(empty response)';
    return [ 'reply' => $reply ];
}

// ============================================================
// Replace {COMPANY_NAME} placeholder in any string
// ============================================================
function nfc_replace_placeholders( $text ) {
    return str_replace( '{COMPANY_NAME}', nfc_get( 'nfc_company_name' ), $text );
}

// ============================================================
// Enqueue assets + pass settings to JS
// ============================================================
add_action( 'wp_enqueue_scripts', 'nfc_enqueue_assets' );
function nfc_enqueue_assets() {
    wp_register_style( 'nfc-chatbot', NFC_PLUGIN_URL . 'assets/chatbot.css', [], NFC_VERSION );
    wp_register_script( 'nfc-chatbot', NFC_PLUGIN_URL . 'assets/chatbot.js', [], NFC_VERSION, true );

    $quick_raw = nfc_get( 'nfc_quick_replies' );
    $quick     = array_values( array_filter( array_map( 'trim', preg_split( '/\r?\n/', $quick_raw ) ) ) );
    $quick     = array_slice( $quick, 0, 4 );

    wp_localize_script( 'nfc-chatbot', 'nfcChatbot', [
        'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
        'nonce'          => wp_create_nonce( 'nfc_chat_nonce' ),
        'title'          => nfc_replace_placeholders( nfc_get( 'nfc_chat_title' ) ),
        'welcomeMessage' => nfc_replace_placeholders( nfc_get( 'nfc_welcome_message' ) ),
        'companyName'    => nfc_get( 'nfc_company_name' ),
        'primaryColor'   => nfc_get( 'nfc_primary_color' ),
        'quickReplies'   => array_map( 'nfc_replace_placeholders', $quick ),
    ] );
}

// ============================================================
// Shortcode + floating widget
// ============================================================
add_shortcode( 'faq_chatbot', 'nfc_shortcode' );
function nfc_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'title'   => '',
        'welcome' => '',
        'color'   => '',
    ], $atts, 'faq_chatbot' );

    wp_enqueue_style( 'nfc-chatbot' );
    wp_enqueue_script( 'nfc-chatbot' );

    $data_attrs = '';
    if ( ! empty( $atts['title'] ) ) {
        $data_attrs .= ' data-title="' . esc_attr( nfc_replace_placeholders( $atts['title'] ) ) . '"';
    }
    if ( ! empty( $atts['welcome'] ) ) {
        $data_attrs .= ' data-welcome="' . esc_attr( nfc_replace_placeholders( $atts['welcome'] ) ) . '"';
    }
    if ( ! empty( $atts['color'] ) && preg_match( '/^#[a-f0-9]{3,6}$/i', $atts['color'] ) ) {
        $data_attrs .= ' data-color="' . esc_attr( $atts['color'] ) . '"';
    }

    return '<div class="nfc-chatbot-inline" data-nfc-chatbot="inline"' . $data_attrs . '></div>';
}

add_action( 'wp_footer', 'nfc_render_floating_widget' );
function nfc_render_floating_widget() {
    if ( '1' !== nfc_get( 'nfc_widget_enabled' ) ) {
        return;
    }
    wp_enqueue_style( 'nfc-chatbot' );
    wp_enqueue_script( 'nfc-chatbot' );
    $pos = nfc_get( 'nfc_widget_position' );
    $allowed = [ 'bottom-right', 'bottom-left', 'top-right', 'top-left' ];
    if ( ! in_array( $pos, $allowed, true ) ) {
        $pos = 'bottom-right';
    }
    echo '<div class="nfc-chatbot-floating nfc-pos-' . esc_attr( $pos ) . '" data-nfc-chatbot="floating"></div>';
}

// ============================================================
// Rate limiting (transient-based, per IP)
// ============================================================
function nfc_get_client_ip() {
    $headers = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
    foreach ( $headers as $h ) {
        if ( ! empty( $_SERVER[ $h ] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) );
            // X-Forwarded-For may be comma-separated
            if ( false !== strpos( $ip, ',' ) ) {
                $ip = trim( explode( ',', $ip )[0] );
            }
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function nfc_check_rate_limit() {
    $max = (int) nfc_get( 'nfc_rate_limit' );
    if ( $max <= 0 ) {
        return true; // disabled
    }
    $ip       = nfc_get_client_ip();
    $key      = 'nfc_rl_' . md5( $ip );
    $count    = (int) get_transient( $key );
    if ( $count >= $max ) {
        return false;
    }
    set_transient( $key, $count + 1, 60 );
    return true;
}

// ============================================================
// AJAX handler
// ============================================================
add_action( 'wp_ajax_nfc_send_message',        'nfc_handle_message' );
add_action( 'wp_ajax_nopriv_nfc_send_message', 'nfc_handle_message' );

function nfc_handle_message() {
    check_ajax_referer( 'nfc_chat_nonce', 'nonce' );

    if ( ! nfc_check_rate_limit() ) {
        wp_send_json_error( [ 'message' => 'Too many messages. Please wait a minute and try again.' ], 429 );
    }

    $faq_data = nfc_get( 'nfc_faq_data' );
    if ( empty( trim( $faq_data ) ) ) {
        wp_send_json_error( [ 'message' => 'Chatbot is not configured. Admin must add FAQ data.' ], 500 );
    }

    $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
    if ( empty( $message ) ) {
        wp_send_json_error( [ 'message' => 'Empty message.' ], 400 );
    }

    $history_raw = isset( $_POST['history'] ) ? sanitize_text_field( wp_unslash( $_POST['history'] ) ) : '[]';
    $history     = json_decode( $history_raw, true );
    if ( ! is_array( $history ) ) {
        $history = [];
    }

    $clean_history = [];
    foreach ( $history as $entry ) {
        if ( ! isset( $entry['role'], $entry['content'] ) ) { continue; }
        if ( ! in_array( $entry['role'], [ 'user', 'assistant' ], true ) ) { continue; }
        $clean_history[] = [
            'role'    => $entry['role'],
            'content' => sanitize_textarea_field( $entry['content'] ),
        ];
    }
    $clean_history[] = [ 'role' => 'user', 'content' => $message ];

    $history_limit = max( 2, (int) nfc_get( 'nfc_history_limit' ) );
    $clean_history = array_slice( $clean_history, -$history_limit );

    $result = nfc_call_provider(
        nfc_get( 'nfc_provider' ),
        nfc_get( 'nfc_model' ),
        nfc_build_system_prompt(),
        $clean_history,
        (int) nfc_get( 'nfc_max_tokens' ),
        (float) nfc_get( 'nfc_temperature' )
    );

    if ( is_wp_error( $result ) ) {
        $status = $result->get_error_data();
        $status = ( is_array( $status ) && isset( $status['status'] ) ) ? $status['status'] : 500;
        wp_send_json_error( [ 'message' => $result->get_error_message() ], $status );
    }

    wp_send_json_success( [ 'reply' => $result['reply'] ] );
}

// ============================================================
// Test connection (admin only)
// ============================================================
add_action( 'wp_ajax_nfc_test_connection', 'nfc_handle_test_connection' );
function nfc_handle_test_connection() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
    }
    check_admin_referer( 'nfc_admin_nonce' );

    $result = nfc_call_provider(
        nfc_get( 'nfc_provider' ),
        nfc_get( 'nfc_model' ),
        'You are a test bot. Reply with exactly: "Test successful."',
        [ [ 'role' => 'user', 'content' => 'Say the test phrase.' ] ],
        64,
        0.1
    );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
    }
    wp_send_json_success( [ 'reply' => $result['reply'] ] );
}
