(function () {
  "use strict";

  const form = document.getElementById("chatForm");
  const input = document.getElementById("chatInput");
  const log = document.getElementById("chatLog");
  const sendBtn = document.getElementById("sendBtn");
  const convId = document.getElementById("convId").value;
  const csrf = document.getElementById("csrf").value;

  function scrollToBottom() {
    log.scrollTop = log.scrollHeight;
  }

  function addMessage(role, text) {
    const wrap = document.createElement("div");
    wrap.className = "msg msg-" + role;
    const roleLabel = document.createElement("div");
    roleLabel.className = "msg-role";
    roleLabel.textContent = role === "user" ? "You" : "FireLam";
    const bubble = document.createElement("div");
    bubble.className = "bubble";
    bubble.textContent = text;
    wrap.appendChild(roleLabel);
    wrap.appendChild(bubble);
    log.appendChild(wrap);
    scrollToBottom();
    return bubble;
  }

  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    addMessage("user", text);
    input.value = "";
    input.style.height = "auto";
    sendBtn.disabled = true;

    const assistantBubble = addMessage("assistant", "");
    assistantBubble.innerHTML = '<span class="typing"><span class="ember-dot"></span> thinking…</span>';
    let started = false;

    try {
      const res = await fetch("/api/chat_stream.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrf,
        },
        body: JSON.stringify({ conversation_id: convId, message: text }),
      });

      if (!res.ok || !res.body) {
        assistantBubble.textContent = "Something went wrong talking to the model (" + res.status + ").";
        sendBtn.disabled = false;
        return;
      }

      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buffer = "";
      let full = "";

      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        const events = buffer.split("\n\n");
        buffer = events.pop(); // last chunk may be incomplete

        for (const evt of events) {
          const line = evt.replace(/^data:\s*/, "").trim();
          if (!line) continue;
          if (line === "[DONE]") continue;

          let payload;
          try {
            payload = JSON.parse(line);
          } catch (err) {
            continue;
          }

          if (payload.error) {
            assistantBubble.textContent = payload.error;
            continue;
          }
          if (typeof payload.delta === "string") {
            if (!started) {
              assistantBubble.textContent = "";
              started = true;
            }
            full += payload.delta;
            assistantBubble.textContent = full;
            scrollToBottom();
          }
        }
      }
    } catch (err) {
      assistantBubble.textContent = "Connection lost before the reply finished.";
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  });

  input.addEventListener("input", function () {
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 160) + "px";
  });

  scrollToBottom();
})();
