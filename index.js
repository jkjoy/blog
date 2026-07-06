document.addEventListener("DOMContentLoaded", () => {
  const button = document.getElementById("to_top");

  if (!button || window.innerWidth <= 480) {
    return;
  }

  const toggleButton = () => {
    button.style.display = window.scrollY > 30 ? "block" : "none";
  };

  window.addEventListener("scroll", toggleButton, { passive: true });

  button.addEventListener("click", (event) => {
    event.preventDefault();
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  toggleButton();
});

document.addEventListener("DOMContentLoaded", () => {
  const uploader = document.querySelector(".attachment-uploader");

  if (!uploader) {
    return;
  }

  const input = uploader.querySelector(".attachment-input");
  const drop = uploader.querySelector(".attachment-drop");
  const list = uploader.querySelector(".attachment-list");
  const editor = document.getElementById("content");
  const uploadUrl = uploader.dataset.uploadUrl || "";
  const csrf = uploader.dataset.csrf || "";
  const maxSize = 30 * 1024 * 1024;

  if (!input || !drop || !list || !editor || !uploadUrl || !csrf) {
    return;
  }

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
});
