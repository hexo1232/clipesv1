// ===========================================
// SIDEBAR2.JS - MENU MOBILE ATUALIZADO
// ===========================================
(function () {
    'use strict';

    document.addEventListener("DOMContentLoaded", inicializarMenuMobile);

    function inicializarMenuMobile() {
        const menuBtnMobile = document.getElementById("menuBtnMobile");
        const mobileMenu = document.getElementById("mobileMenu");
        const closeMobileMenu = document.getElementById("closeMobileMenu");

        // Abrir menu
        function abrirMenu() {
            mobileMenu.classList.add("active");
            mobileMenu.classList.remove("hidden");
            document.body.style.overflow = "hidden";
        }

        // Fechar menu
        function fecharMenu() {
            mobileMenu.classList.remove("active");
            mobileMenu.classList.add("hidden");
            document.body.style.overflow = "";
        }

        // Eventos abrir / fechar
        if (menuBtnMobile) {
            menuBtnMobile.addEventListener("click", (e) => {
                e.stopPropagation();
                abrirMenu();
            });
        }

        if (closeMobileMenu) {
            closeMobileMenu.addEventListener("click", (e) => {
                e.stopPropagation();
                fecharMenu();
            });
        }

        // Fechar ao clicar em link
        document.querySelectorAll(".sidebar-links a").forEach(a => {
            a.addEventListener("click", () => setTimeout(fecharMenu, 150));
        });

        // Fechar com ESC
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && mobileMenu.classList.contains("active")) {
                fecharMenu();
            }
        });

        // ===============================
        // DROPDOWN DO PERFIL NA SIDEBAR
        // ===============================
        const sidebarUserProfile = document.getElementById("sidebarUserProfile");
        const sidebarProfileDropdown = document.getElementById("sidebarProfileDropdown");
        const sidebarArrow = document.getElementById("sidebarArrow");

        if (sidebarUserProfile && sidebarProfileDropdown) {
            sidebarUserProfile.addEventListener("click", (e) => {
                e.stopPropagation();
                sidebarProfileDropdown.classList.toggle("active");
                sidebarArrow.classList.toggle("open"); // seta gira se configurado no CSS
            });

            sidebarProfileDropdown.querySelectorAll("a").forEach(link =>
                link.addEventListener("click", () => setTimeout(fecharMenu, 150))
            );
        }

        // Impedir scroll lateral no mobile
        if (window.innerWidth <= 768) document.body.style.overflowX = "hidden";
        window.addEventListener("resize", () => {
            document.body.style.overflowX = window.innerWidth <= 768 ? "hidden" : "";
            if (window.innerWidth > 768) fecharMenu();
        });
    }

    // Sincronizar contadores (desktop ↔ mobile)
    window.atualizarContador = function (id, valor) {
        const desktop = document.getElementById(id);
        const mobile = document.getElementById(id + "Mobile");
        if (desktop) desktop.style.display = valor > 0 ? "inline-block" : "none";
        if (mobile) mobile.style.display = valor > 0 ? "inline-block" : "none";
        if (desktop) desktop.innerText = valor;
        if (mobile) mobile.innerText = valor;
    };

})();
