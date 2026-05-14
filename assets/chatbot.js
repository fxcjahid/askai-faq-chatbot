(function () {
  "use strict";

  if (typeof nfcChatbot === "undefined") return;

  // ============================================================
  // Build chat UI inside a given container
  // ============================================================
  function buildChat(container, variant) {
    const isFloating = variant === "floating";

    // Per-instance overrides via shortcode data attributes
    const title          = container.getAttribute("data-title")   || nfcChatbot.title;
    const welcome        = container.getAttribute("data-welcome") || nfcChatbot.welcomeMessage;
    const color          = container.getAttribute("data-color")   || nfcChatbot.primaryColor;
    const quickReplies   = nfcChatbot.quickReplies || [];

    if (color) {
      container.style.setProperty("--nfc-primary", color);
      container.style.setProperty("--nfc-primary-dark", shadeColor(color, -15));
      container.style.setProperty("--nfc-gradient", `linear-gradient(135deg, ${color}, ${shadeColor(color, -25)})`);
    }

    container.innerHTML = `
      ${
        isFloating
          ? `<button class="nfc-bubble" aria-label="Open chat">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
              </svg>
            </button>`
          : ""
      }
      <div class="nfc-window ${isFloating ? "nfc-window-floating" : "nfc-window-inline"}">
        <div class="nfc-header">
          <div class="nfc-avatar">AI</div>
          <div class="nfc-header-text">
            <div class="nfc-title"></div>
            <span class="nfc-status">Online</span>
          </div>
          ${isFloating ? '<button class="nfc-close" aria-label="Close chat">&times;</button>' : ""}
        </div>
        <div class="nfc-messages"></div>
        <div class="nfc-quick-replies"></div>
        <form class="nfc-input-row">
          <input type="text" class="nfc-input" placeholder="Type your question..." autocomplete="off" required />
          <button type="submit" class="nfc-send">Send</button>
        </form>
      </div>
    `;

    const titleEl     = container.querySelector(".nfc-title");
    const messagesEl  = container.querySelector(".nfc-messages");
    const quickEl     = container.querySelector(".nfc-quick-replies");
    const formEl      = container.querySelector(".nfc-input-row");
    const inputEl     = container.querySelector(".nfc-input");
    const sendEl      = container.querySelector(".nfc-send");
    const windowEl    = container.querySelector(".nfc-window");
    const bubbleEl    = container.querySelector(".nfc-bubble");
    const closeEl     = container.querySelector(".nfc-close");

    titleEl.textContent = title;
    addMessage(messagesEl, "bot", welcome);
    renderQuickReplies(quickEl, quickReplies, (q) => submitMessage(q));

    if (isFloating) {
      bubbleEl.addEventListener("click", () => {
        windowEl.classList.toggle("nfc-open");
        if (windowEl.classList.contains("nfc-open")) inputEl.focus();
      });
      closeEl.addEventListener("click", () => windowEl.classList.remove("nfc-open"));
    }

    const history = [];

    async function submitMessage(text) {
      text = text.trim();
      if (!text) return;

      // Hide quick replies after first interaction
      quickEl.style.display = "none";

      addMessage(messagesEl, "user", text);
      history.push({ role: "user", content: text });
      inputEl.value = "";
      setLoading(true, inputEl, sendEl);

      const typing = addTypingIndicator(messagesEl);

      try {
        const reply = await sendToServer(text, history.slice(0, -1));
        typing.remove();
        addMessage(messagesEl, "bot", reply);
        history.push({ role: "assistant", content: reply });
      } catch (err) {
        typing.remove();
        addMessage(messagesEl, "bot", "⚠️ " + err.message);
      } finally {
        setLoading(false, inputEl, sendEl);
        inputEl.focus();
      }
    }

    formEl.addEventListener("submit", (e) => {
      e.preventDefault();
      submitMessage(inputEl.value);
    });
  }

  // ============================================================
  // Helpers
  // ============================================================
  function shadeColor(hex, percent) {
    const h = hex.replace("#", "");
    const num = parseInt(h.length === 3 ? h.split("").map((c) => c + c).join("") : h, 16);
    const amt = Math.round(2.55 * percent);
    const r = Math.max(0, Math.min(255, (num >> 16) + amt));
    const g = Math.max(0, Math.min(255, ((num >> 8) & 0xff) + amt));
    const b = Math.max(0, Math.min(255, (num & 0xff) + amt));
    return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  }

  // Minimal safe markdown renderer (bold, italic, links, code, line breaks, lists)
  function renderMarkdown(text) {
    // 1. Escape HTML
    let out = text
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");

    // 2. Code blocks ```...```
    out = out.replace(/```([\s\S]*?)```/g, (_, c) => `<pre><code>${c.trim()}</code></pre>`);

    // 3. Inline code `...`
    out = out.replace(/`([^`\n]+)`/g, "<code>$1</code>");

    // 4. Bold **...**
    out = out.replace(/\*\*([^*\n]+)\*\*/g, "<strong>$1</strong>");

    // 5. Italic *...*  (avoid clashing with bold by requiring non-* boundaries)
    out = out.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, "$1<em>$2</em>");

    // 6. Links [text](url)
    out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

    // 7. Auto-link bare URLs
    out = out.replace(/(^|\s)(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>');

    // 8. Bullet lists (lines starting with - or *)
    out = out.replace(/(^|\n)([-*] .+(?:\n[-*] .+)*)/g, (_, pre, block) => {
      const items = block.split("\n").map((l) => "<li>" + l.replace(/^[-*]\s+/, "") + "</li>").join("");
      return pre + "<ul>" + items + "</ul>";
    });

    // 9. Numbered lists
    out = out.replace(/(^|\n)(\d+\. .+(?:\n\d+\. .+)*)/g, (_, pre, block) => {
      const items = block.split("\n").map((l) => "<li>" + l.replace(/^\d+\.\s+/, "") + "</li>").join("");
      return pre + "<ol>" + items + "</ol>";
    });

    // 10. Line breaks (preserve paragraphs)
    out = out.replace(/\n{2,}/g, "</p><p>");
    out = out.replace(/\n/g, "<br>");
    out = "<p>" + out + "</p>";

    return out;
  }

  function addMessage(parent, role, text) {
    const wrap = document.createElement("div");
    wrap.className = "nfc-message nfc-" + role;
    const bubble = document.createElement("div");
    bubble.className = "nfc-bubble-msg";
    if (role === "bot") {
      bubble.innerHTML = renderMarkdown(text);
    } else {
      bubble.textContent = text;
    }
    wrap.appendChild(bubble);
    parent.appendChild(wrap);
    parent.scrollTop = parent.scrollHeight;
    return wrap;
  }

  function addTypingIndicator(parent) {
    const wrap = document.createElement("div");
    wrap.className = "nfc-message nfc-bot nfc-typing";
    wrap.innerHTML = '<div class="nfc-bubble-msg"><span class="nfc-dot"></span><span class="nfc-dot"></span><span class="nfc-dot"></span></div>';
    parent.appendChild(wrap);
    parent.scrollTop = parent.scrollHeight;
    return wrap;
  }

  function renderQuickReplies(container, replies, onClick) {
    if (!replies || replies.length === 0) {
      container.style.display = "none";
      return;
    }
    container.innerHTML = "";
    replies.forEach((q) => {
      const chip = document.createElement("button");
      chip.type = "button";
      chip.className = "nfc-quick-reply";
      chip.textContent = q;
      chip.addEventListener("click", () => onClick(q));
      container.appendChild(chip);
    });
  }

  function setLoading(loading, input, btn) {
    input.disabled = loading;
    btn.disabled = loading;
    btn.textContent = loading ? "..." : "Send";
  }

  async function sendToServer(message, history) {
    const formData = new FormData();
    formData.append("action", "nfc_send_message");
    formData.append("nonce", nfcChatbot.nonce);
    formData.append("message", message);
    formData.append("history", JSON.stringify(history));

    const res = await fetch(nfcChatbot.ajaxUrl, { method: "POST", body: formData });
    const data = await res.json();

    if (!data.success) {
      throw new Error(data.data?.message || "Request failed");
    }
    return data.data.reply;
  }

  // ============================================================
  // Init all chatbot instances on the page
  // ============================================================
  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-nfc-chatbot]").forEach((el) => {
      buildChat(el, el.getAttribute("data-nfc-chatbot"));
    });
  });
})();
