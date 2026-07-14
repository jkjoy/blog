function initAccountMenus() {
  const accountMenus = Array.from(document.querySelectorAll("[data-admin-account]"));
  if (!accountMenus.length) return;

  accountMenus.forEach((menu) => {
    menu.addEventListener("toggle", () => {
      if (!menu.open) return;
      accountMenus.forEach((otherMenu) => {
        if (otherMenu !== menu) {
          otherMenu.open = false;
        }
      });
    });
  });

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Node)) return;
    accountMenus.forEach((menu) => {
      if (!menu.contains(target)) {
        menu.open = false;
      }
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    accountMenus.forEach((menu) => {
      if (!menu.open) return;
      menu.open = false;
      const toggle = menu.querySelector("summary");
      if (toggle instanceof HTMLElement) {
        toggle.focus();
      }
    });
  });
}

function initSettingsControls() {
  const prettyUrl = document.getElementById("pretty_url");
  const rewriteHelp = document.querySelector("[data-rewrite-help]");
  if (prettyUrl && rewriteHelp) {
    const toggleRewriteHelp = () => {
      rewriteHelp.hidden = prettyUrl.value !== "1";
    };
    prettyUrl.addEventListener("change", toggleRewriteHelp);
    toggleRewriteHelp();
  }

  document.querySelectorAll("[data-check-all]").forEach((control) => {
    control.addEventListener("change", () => {
      const name = control.dataset.checkAll;
      document.querySelectorAll(`input[name="${name}"]`).forEach((checkbox) => {
        checkbox.checked = control.checked;
      });
    });
  });
}

function initAttachmentUploader() {
  const uploader = document.querySelector(".attachment-uploader");
  if (!uploader) return;

  const input = uploader.querySelector(".attachment-input");
  const drop = uploader.querySelector(".attachment-drop");
  const list = uploader.querySelector(".attachment-list");
  const editor = document.getElementById("content");
  const uploadUrl = uploader.dataset.uploadUrl || "";
  const csrf = uploader.dataset.csrf || "";
  const maxSize = 30 * 1024 * 1024;

  if (!input || !drop || !list || !editor || !uploadUrl || !csrf) return;

  const appendMarkdown = (markdown) => {
    const start = editor.selectionStart ?? editor.value.length;
    const end = editor.selectionEnd ?? editor.value.length;
    const before = editor.value.slice(0, start);
    const after = editor.value.slice(end);
    const prefix = before === "" || before.endsWith("\n") ? "" : "\n";
    const insert = `${prefix}${markdown}\n`;

    editor.value = before + insert + after;
    const cursor = before.length + insert.length;
    editor.focus();
    editor.setSelectionRange(cursor, cursor);
  };

  const createItem = (file) => {
    const item = document.createElement("div");
    item.className = "attachment-item";

    const preview = document.createElement("div");
    preview.className = "attachment-preview";
    if (file.type.startsWith("image/")) {
      const image = document.createElement("img");
      image.alt = file.name;
      image.src = URL.createObjectURL(file);
      image.onload = () => URL.revokeObjectURL(image.src);
      preview.appendChild(image);
    } else {
      preview.textContent = "FILE";
    }

    const body = document.createElement("div");
    body.className = "attachment-item__body";

    const name = document.createElement("strong");
    name.textContent = file.name;

    const status = document.createElement("span");
    status.textContent = file.size > maxSize ? "文件超过 30M" : "等待上传";

    body.append(name, status);
    item.append(preview, body);
    list.appendChild(item);

    return { item, status };
  };

  const uploadFiles = async (files) => {
    const selected = Array.from(files || []);

    for (const file of selected) {
      const row = createItem(file);
      if (file.size > maxSize) {
        row.item.classList.add("is-error");
        continue;
      }

      row.status.textContent = "上传中...";

      const data = new FormData();
      data.append("csrf_token", csrf);
      data.append("attachments[]", file);

      try {
        const response = await fetch(uploadUrl, {
          method: "POST",
          body: data,
          credentials: "same-origin",
        });
        const result = await response.json();

        if (!response.ok || !result.ok || !result.files?.length) {
          const message = result.errors?.[0]?.error || result.error || "上传失败";
          throw new Error(message);
        }

        const uploaded = result.files[0];
        row.item.classList.add("is-done");
        row.status.textContent = "已上传并插入 Markdown";
        appendMarkdown(uploaded.markdown);

        if (uploaded.is_image) {
          const image = row.item.querySelector(".attachment-preview img");
          if (image) {
            image.src = uploaded.url;
          }
        }
      } catch (error) {
        row.item.classList.add("is-error");
        row.status.textContent = error instanceof Error ? error.message : "上传失败";
      }
    }

    input.value = "";
  };

  input.addEventListener("change", () => uploadFiles(input.files));

  ["dragenter", "dragover"].forEach((type) => {
    drop.addEventListener(type, (event) => {
      event.preventDefault();
      drop.classList.add("is-dragging");
    });
  });

  ["dragleave", "drop"].forEach((type) => {
    drop.addEventListener(type, (event) => {
      event.preventDefault();
      drop.classList.remove("is-dragging");
    });
  });

  drop.addEventListener("drop", (event) => {
    uploadFiles(event.dataTransfer?.files);
  });
}

function initAiEditor() {
  const root = document.querySelector("[data-ai-editor]");
  if (!root) return;

  const title = document.getElementById("title");
  const slug = document.getElementById("slug");
  const excerpt = document.getElementById("excerpt");
  const content = document.getElementById("content");
  const modal = root.querySelector("[data-ai-modal]");
  const instruction = root.querySelector("#ai_instruction");
  const status = root.querySelector("[data-ai-status]");
  const confirm = root.querySelector("[data-ai-confirm]");

  const generate = async (type, source, extraInstruction = "") => {
    const data = new FormData();
    data.append("csrf_token", root.dataset.csrf || "");
    data.append("type", type);
    data.append("content", source);
    data.append("instruction", extraInstruction);

    const response = await fetch(root.dataset.url || "", {
      method: "POST",
      body: data,
      credentials: "same-origin",
    });
    const result = await response
      .json()
      .catch(() => ({ ok: false, error: "AI 服务返回了无法解析的响应。" }));

    if (!response.ok || !result.ok) {
      throw new Error(result.error || "AI 生成失败。");
    }
    return result.result || "";
  };

  document.querySelectorAll("[data-ai-action]").forEach((button) => {
    button.addEventListener("click", async () => {
      const type = button.dataset.aiAction;
      if (type === "polish") {
        modal.hidden = false;
        instruction.focus();
        return;
      }

      const source = type === "slug" ? title.value.trim() : content.value.trim();
      const original = button.textContent;
      button.disabled = true;
      button.textContent = "生成中...";

      try {
        const result = await generate(type, source);
        if (type === "slug") slug.value = result;
        if (type === "summary") excerpt.value = result;
      } catch (error) {
        window.alert(error instanceof Error ? error.message : "AI 生成失败。");
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    });
  });

  root.querySelectorAll("[data-ai-close]").forEach((button) => {
    button.addEventListener("click", () => {
      modal.hidden = true;
      status.textContent = "";
    });
  });

  confirm.addEventListener("click", async () => {
    confirm.disabled = true;
    status.textContent = "AI 正在处理正文...";

    try {
      content.value = await generate("polish", content.value, instruction.value.trim());
      modal.hidden = true;
      status.textContent = "";
      content.focus();
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : "AI 生成失败。";
    } finally {
      confirm.disabled = false;
    }
  });
}

function initComments() {
  document.querySelectorAll(".comments").forEach((root) => {
    const form = root.querySelector(".comment-form");
    if (!form) {
      root.addEventListener("click", (event) => event.stopPropagation());
      return;
    }

    const parentInput = form.querySelector("[data-comment-parent-id]");
    const replyState = form.querySelector("[data-comment-reply-state]");
    const replyName = form.querySelector("[data-comment-reply-name]");
    const cancelButton = form.querySelector("[data-comment-reply-cancel]");
    const content = form.querySelector("#comment-content");
    const replyButtons = [...root.querySelectorAll("[data-comment-reply]")];
    let activeReplyButton = null;

    const setReply = (button, focusContent = true) => {
      const commentId = button.dataset.commentId || "";
      const author = button.dataset.commentAuthor || "";
      if (!parentInput || !replyState || !replyName || !content || !/^\d+$/.test(commentId)) return;

      parentInput.value = commentId;
      replyState.hidden = false;
      activeReplyButton = button;
      replyButtons.forEach((item) => {
        item.setAttribute("aria-pressed", item === button ? "true" : "false");
      });
      cancelButton?.setAttribute("aria-label", `取消回复 @${author}`);

      if (focusContent) {
        replyName.textContent = "";
        requestAnimationFrame(() => {
          if (parentInput.value !== commentId) return;
          replyName.textContent = `@${author}`;
          form.scrollIntoView({
            behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
            block: "start",
          });
          requestAnimationFrame(() => {
            if (parentInput.value === commentId) {
              content.focus({ preventScroll: true });
            }
          });
        });
      } else {
        replyName.textContent = `@${author}`;
      }
    };

    const clearReply = () => {
      if (!parentInput || !replyState || !replyName) return;
      const returnTarget = activeReplyButton;
      parentInput.value = "";
      replyName.textContent = "";
      replyState.hidden = true;
      activeReplyButton = null;
      replyButtons.forEach((item) => item.setAttribute("aria-pressed", "false"));
      cancelButton?.setAttribute("aria-label", "取消回复");
      (returnTarget || content)?.focus();
    };

    root.addEventListener("click", (event) => {
      event.stopPropagation();
      if (!(event.target instanceof Element)) return;

      const replyButton = event.target.closest("[data-comment-reply]");
      if (replyButton && root.contains(replyButton)) {
        setReply(replyButton);
        return;
      }

      if (event.target.closest("[data-comment-reply-cancel]")) clearReply();
    });

    const initialReply = replyButtons.find((button) => button.dataset.commentId === parentInput?.value);
    if (initialReply) setReply(initialReply, false);
  });
}

function initTerminal() {
  const term = document.querySelector(".terminal");
  const output = document.querySelector("#output");
  const input = document.querySelector("#input");
  const shown = document.querySelector("#input-text");
  const ghost = document.querySelector("#ghost-text");
  const scan = document.querySelector("#scanlines");
  if (!term || !output || !input || !shown || !ghost || !scan) return;

  const history = [];
  let historyIndex = 0;
  const routes = {
    home: term.dataset.home,
    tags: term.dataset.tags,
    links: term.dataset.links,
    archives: term.dataset.archives,
  };
  const commands = [
    "help",
    "ls",
    "cat",
    "cd",
    "pwd",
    "clear",
    "history",
    "theme",
    "crt",
    "date",
    "home",
    "tags",
    "links",
    "archives",
  ];

  const print = (text, className = "") => {
    const line = document.createElement("div");
    line.className = `line ${className}`;
    line.textContent = text;
    output.append(line);
    output.scrollTop = output.scrollHeight;
  };

  const syncInput = () => {
    shown.textContent = input.value;
    const hit = commands.find((command) => command.startsWith(input.value) && command !== input.value);
    ghost.textContent = input.value && hit ? hit.slice(input.value.length) : "";
  };

  const switchTheme = (name) => {
    const themes = {
      phosphor: ["#7ec699", "#a8e8a8"],
      amber: ["#e8a87c", "#ffb86c"],
      cyan: ["#7aa6da", "#a8d0f0"],
    };
    const theme = themes[name];
    if (!theme) {
      print("themes: phosphor, amber, cyan", "dim");
      return;
    }

    document.documentElement.style.setProperty("--green", theme[0]);
    document.documentElement.style.setProperty("--bright", theme[1]);
    print(`theme: switched to ${name}`, "green");
  };

  const runCommand = (raw) => {
    const value = raw.trim();
    const [command, argument] = value.split(/\s+/, 2);
    print(`visitor@devlog:~$ ${value}`, "cmd-echo");

    if (!value) return;
    if (routes[command]) {
      location.href = routes[command];
      return;
    }
    if (command === "clear") {
      output.innerHTML = "";
      return;
    }
    if (command === "pwd") {
      print("~", "green");
      return;
    }
    if (command === "date") {
      print(new Date().toString(), "green");
      return;
    }
    if (command === "crt") {
      scan.classList.toggle("disabled");
      print(`CRT scanlines: ${scan.classList.contains("disabled") ? "disabled" : "enabled"}`, "dim");
      return;
    }
    if (command === "history") {
      history.forEach((item, index) => print(`${String(index + 1).padStart(4)}  ${item}`, "dim"));
      return;
    }
    if (command === "theme") {
      switchTheme(argument);
      return;
    }
    if (command === "ls") {
      print("home/  tags/  links/  archives/  rss.xml", "green");
      document.querySelectorAll(".posts .post a").forEach((link) => print(`${link.textContent}.md`, "blue"));
      return;
    }
    if (command === "cd" || command === "cat") {
      print(`Use: ${command === "cd" ? "cd tags" : "open an article link from ls"}`, "dim");
      return;
    }
    if (command === "help") {
      print("COMMANDS", "amber");
      print("  ls                         list posts and sections");
      print("  home|tags|links|archives   navigate site");
      print("  clear|history|pwd          shell utilities");
      print("  theme <name>               phosphor, amber, cyan");
      print("  crt|date                    display controls");
      return;
    }

    print(`${command}: command not found. Type "help".`, "red");
  };

  input.addEventListener("input", syncInput);
  input.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      if (input.value.trim()) {
        history.push(input.value.trim());
        historyIndex = history.length;
      }
      runCommand(input.value);
      input.value = "";
      syncInput();
    } else if (event.key === "Tab" && ghost.textContent) {
      event.preventDefault();
      input.value += ghost.textContent;
      syncInput();
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      if (historyIndex > 0) input.value = history[--historyIndex] || "";
      syncInput();
    } else if (event.key === "ArrowDown") {
      event.preventDefault();
      input.value =
        historyIndex < history.length - 1 ? history[++historyIndex] : ((historyIndex = history.length), "");
      syncInput();
    } else if (event.ctrlKey && event.key.toLowerCase() === "l") {
      event.preventDefault();
      output.innerHTML = "";
    }
  });

  document.addEventListener("click", () => input.focus());

  const updateSize = () => {
    const info = document.querySelector("#term-info");
    if (info) {
      info.textContent = `${Math.floor(output.clientWidth / 8)}×${Math.floor(output.clientHeight / 16)}`;
    }
  };

  addEventListener("resize", updateSize);
  updateSize();
  setTimeout(() => document.querySelector("#turn-on")?.remove(), 800);
  input.focus();
}

document.addEventListener("DOMContentLoaded", () => {
  initAccountMenus();
  initSettingsControls();
  initAttachmentUploader();
  initAiEditor();
  initComments();
  initTerminal();
});
