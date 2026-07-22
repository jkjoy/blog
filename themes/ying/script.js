(function () {
  "use strict";

  var root = document.documentElement;
  var menuButton = document.getElementById("menu-btn");
  var menuClose = document.getElementById("close-menu");
  var mainMenu = document.getElementById("main-menu");
  var menuBackdrop = document.getElementById("menu-backdrop");
  var darkToggle = document.getElementById("toggle-dark-mode");
  var backToTop = document.getElementById("back-to-top");
  var themeColor = document.querySelector("[data-ying-theme-color]");

  function setMenu(open) {
    if (!mainMenu || !menuButton) {
      return;
    }
    mainMenu.classList.toggle("active", open);
    if (menuBackdrop) {
      menuBackdrop.classList.toggle("active", open);
    }
    menuButton.setAttribute("aria-expanded", open ? "true" : "false");
    document.body.style.overflow = open ? "hidden" : "";
    if (open && menuClose) {
      menuClose.focus();
    } else if (!open && document.activeElement === menuClose) {
      menuButton.focus();
    }
  }

  if (menuButton) {
    menuButton.addEventListener("click", function () {
      setMenu(true);
    });
  }

  if (menuClose) {
    menuClose.addEventListener("click", function () {
      setMenu(false);
    });
  }

  if (menuBackdrop) {
    menuBackdrop.addEventListener("click", function () {
      setMenu(false);
    });
  }

  if (mainMenu) {
    mainMenu.addEventListener("click", function (event) {
      if (event.target.closest("a")) {
        setMenu(false);
      }
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      setMenu(false);
    }
  });

  function applyDarkMode(dark) {
    root.classList.toggle("dark", dark);
    if (darkToggle) {
      var icon = darkToggle.querySelector("i");
      darkToggle.setAttribute("aria-pressed", dark ? "true" : "false");
      darkToggle.setAttribute("aria-label", dark ? "切换浅色模式" : "切换深色模式");
      darkToggle.setAttribute("title", dark ? "白天模式" : "黑夜模式");
      if (icon) {
        icon.className = dark ? "ri-sun-line" : "ri-moon-line";
      }
    }
    if (themeColor) {
      themeColor.setAttribute("content", dark ? "#292524" : "#f3f4f6");
    }
  }

  applyDarkMode(root.classList.contains("dark"));

  if (darkToggle) {
    darkToggle.addEventListener("click", function () {
      var dark = !root.classList.contains("dark");
      try {
        localStorage.setItem("darkMode", dark ? "true" : "false");
      } catch (error) {
      }
      applyDarkMode(dark);
    });
  }

  function updateBackToTop() {
    if (!backToTop) {
      return;
    }
    var visible = window.scrollY > 300;
    backToTop.classList.toggle("opacity-0", !visible);
    backToTop.classList.toggle("pointer-events-none", !visible);
    backToTop.classList.toggle("opacity-100", visible);
    backToTop.classList.toggle("pointer-events-auto", visible);
  }

  if (backToTop) {
    window.addEventListener("scroll", updateBackToTop, { passive: true });
    backToTop.addEventListener("click", function () {
      window.scrollTo({
        top: 0,
        behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth"
      });
    });
    updateBackToTop();
  }
})();
