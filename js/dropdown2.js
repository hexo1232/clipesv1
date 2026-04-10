window.addEventListener('DOMContentLoaded', () => {
    const usuarioDropdown = document.getElementById('usuarioDropdown');
    const menuPerfil = document.getElementById('menuPerfil');

    if (!usuarioDropdown || !menuPerfil) return;

    usuarioDropdown.addEventListener('click', (e) => {
        e.stopPropagation();
        menuPerfil.style.display =
            menuPerfil.style.display === "flex" ? "none" : "flex";
    });

    document.addEventListener("click", () => {
        menuPerfil.style.display = "none";
    });
});


