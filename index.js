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
