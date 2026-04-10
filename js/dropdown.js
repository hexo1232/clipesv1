window.addEventListener('DOMContentLoaded', () => {
    /* ==========================
        DROPDOWN DESKTOP
    ========================== */
    const usuarioDropdown = document.getElementById('usuarioDropdown');
    const menuPerfil = document.getElementById('menuPerfil');

    if (usuarioDropdown && menuPerfil) {
        usuarioDropdown.addEventListener('click', (e) => {
            e.stopPropagation();

            const estaVisivel = menuPerfil.style.display === 'flex';
            menuPerfil.style.display = estaVisivel ? 'none' : 'flex';
        });

        document.addEventListener('click', (e) => {
            if (!usuarioDropdown.contains(e.target)) {
                menuPerfil.style.display = 'none';
            }
        });
    } else {
        console.warn("⚠️ Elementos 'usuarioDropdown' ou 'menuPerfil' não encontrados no DOM.");
    }


    /* ==========================
        DROPDOWN MOBILE
    ========================== */
    const sidebarUserProfile = document.getElementById('sidebarUserProfile');
    const sidebarProfileDropdown = document.getElementById('sidebarProfileDropdown');
    const sidebarArrow = document.getElementById('sidebarArrow'); // ← NOVO

    if (sidebarUserProfile && sidebarProfileDropdown) {
        sidebarUserProfile.addEventListener('click', (e) => {
            e.stopPropagation();

            const ativo = sidebarProfileDropdown.classList.contains('active');

            if (ativo) {
                sidebarProfileDropdown.classList.remove('active');
                sidebarUserProfile.classList.remove('active');
                if (sidebarArrow) sidebarArrow.classList.remove('active'); // ← NOVO
            } else {
                sidebarProfileDropdown.classList.add('active');
                sidebarUserProfile.classList.add('active');
                if (sidebarArrow) sidebarArrow.classList.add('active'); // ← NOVO
            }
        });

        // fechar ao clicar fora
     document.addEventListener('click', (e) => {
    if (!sidebarUserProfile.contains(e.target) && !sidebarProfileDropdown.contains(e.target)) {
        sidebarProfileDropdown.classList.remove('active');
        sidebarUserProfile.classList.remove('active');
        if (sidebarArrow) sidebarArrow.classList.remove('active');
    }
});

    } else {
        console.warn("⚠️ Elementos do dropdown MOBILE não encontrados.");
    }
});
