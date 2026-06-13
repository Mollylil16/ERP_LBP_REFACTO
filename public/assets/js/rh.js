document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("moduleSidebar");
  const menuButton = document.querySelector("[data-module-menu]");

  menuButton?.addEventListener("click", () => {
    sidebar?.classList.toggle("is-open");
  });

  document.querySelectorAll("[data-coming-soon]").forEach((link) => {
    link.addEventListener("click", (event) => event.preventDefault());
  });
});
