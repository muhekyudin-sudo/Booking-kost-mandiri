(() => {
  const trigger = document.getElementById("account-trigger");
  const sidebar = document.getElementById("account-sidebar");
  const backdrop = document.getElementById("account-sidebar-backdrop");
  const closeButton = sidebar?.querySelector(".account-sidebar-close");

  if (!trigger || !sidebar || !backdrop || !closeButton) return;

  let closeTimer;

  const closeSidebar = () => {
    trigger.setAttribute("aria-expanded", "false");
    sidebar.classList.remove("is-open");
    backdrop.classList.remove("is-open");
    document.body.classList.remove("account-sidebar-open");
    window.clearTimeout(closeTimer);
    closeTimer = window.setTimeout(() => {
      sidebar.hidden = true;
      backdrop.hidden = true;
      trigger.focus();
    }, 220);
  };

  trigger.addEventListener("click", () => {
    window.clearTimeout(closeTimer);
    sidebar.hidden = false;
    backdrop.hidden = false;
    trigger.setAttribute("aria-expanded", "true");
    document.body.classList.add("account-sidebar-open");
    window.requestAnimationFrame(() => {
      sidebar.classList.add("is-open");
      backdrop.classList.add("is-open");
      closeButton.focus();
    });
  });

  closeButton.addEventListener("click", closeSidebar);
  backdrop.addEventListener("click", closeSidebar);

  document.addEventListener("keydown", (event) => {
    if (sidebar.hidden) return;
    if (event.key === "Escape") {
      closeSidebar();
    } else if (event.key === "Tab") {
      event.preventDefault();
      closeButton.focus();
    }
  });
})();
