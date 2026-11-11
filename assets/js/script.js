
document.addEventListener("DOMContentLoaded", function () {
    const menuButton = document.querySelector("[data-menu-toggle]");
    const sidebar = document.querySelector(".sidebar");

    if (menuButton && sidebar) {
        menuButton.addEventListener("click", function () {
            sidebar.classList.toggle("open");
        });
    }

    document.querySelectorAll("[data-confirm]").forEach(function (button) {
        button.addEventListener("click", function (event) {
            const message = button.dataset.confirm || "Apakah Anda yakin?";

            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
