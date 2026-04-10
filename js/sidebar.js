// ==========================================
// SCRIPT DE CONTROLE DA SIDEBAR MOBILE
// ==========================================

document.addEventListener('DOMContentLoaded', function() {
    // Elementos
    const menuBtn = document.querySelector('.menu-btn');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    const closeSidebar = document.querySelector('.sidebar .close-sidebar');
    const main = document.querySelector('.main');
    const conteudo = document.querySelector('.conteudo');
    const body = document.body;

    // Função para abrir sidebar
    function openSidebar() {
        sidebar.classList.add('show');
        sidebarOverlay.classList.add('show');
        main.classList.add('sidebar-open');
        conteudo.classList.add('sidebar-open');
        body.classList.add('menu-open');
    }

    // Função para fechar sidebar
    function closeSidebarFunc() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        main.classList.remove('sidebar-open');
        conteudo.classList.remove('sidebar-open');
        body.classList.remove('menu-open');
    }

    // Função para toggle (alternar)
    function toggleSidebar() {
        if (sidebar.classList.contains('show')) {
            closeSidebarFunc();
        } else {
            openSidebar();
        }
    }

    // Event Listeners
    if (menuBtn) {
        menuBtn.addEventListener('click', toggleSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebarFunc);
    }

    if (closeSidebar) {
        closeSidebar.addEventListener('click', closeSidebarFunc);
    }

    // Fecha sidebar ao clicar em links (opcional)
    const sidebarLinks = document.querySelectorAll('.sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Em mobile, fecha a sidebar após clicar em um link
            if (window.innerWidth < 1200) {
                closeSidebarFunc();
            }
        });
    });

    // Fecha sidebar ao redimensionar para desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1200) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            main.classList.remove('sidebar-open');
            conteudo.classList.remove('sidebar-open');
            body.classList.remove('menu-open');
        }
    });

    // Suporte para tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebarFunc();
        }
    });

    // Previne scroll do body quando sidebar está aberta
    const mediaQuery = window.matchMedia('(max-width: 1199px)');
    
    function handleSidebarScroll() {
        if (mediaQuery.matches && sidebar.classList.contains('show')) {
            body.style.overflow = 'hidden';
        } else {
            body.style.overflow = '';
        }
    }

    // Observa mudanças na classe show da sidebar
    const observer = new MutationObserver(handleSidebarScroll);
    if (sidebar) {
        observer.observe(sidebar, { 
            attributes: true, 
            attributeFilter: ['class'] 
        });
    }
});

// ==========================================
// FUNÇÕES AUXILIARES PARA USO EXTERNO
// ==========================================

// Função global para abrir sidebar (pode ser chamada de outros scripts)
window.openSidebar = function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const main = document.querySelector('.main');
    const conteudo = document.querySelector('.conteudo');
    const body = document.body;
    
    if (sidebar) {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        main.classList.add('sidebar-open');
        conteudo.classList.add('sidebar-open');
        body.classList.add('menu-open');
    }
};

// Função global para fechar sidebar
window.closeSidebar = function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const main = document.querySelector('.main');
    const conteudo = document.querySelector('.conteudo');
    const body = document.body;
    
    if (sidebar) {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        main.classList.remove('sidebar-open');
        conteudo.classList.remove('sidebar-open');
        body.classList.remove('menu-open');
    }
};

// Função global para alternar sidebar
window.toggleSidebar = function() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar && sidebar.classList.contains('show')) {
        window.closeSidebar();
    } else {
        window.openSidebar();
    }
};