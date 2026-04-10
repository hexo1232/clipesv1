document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.getElementById("darkToggle");
  const carrinho = document.getElementById("iconeCarrinho");
   const cadeado = document.getElementById("iconecadeado");
    const user = document.getElementById("iconeuser");
  const logouts = document.getElementsByClassName("iconelogout");

  let suporteLocalStorage = true;
  try {
    localStorage.setItem("__teste__", "ok");
    localStorage.removeItem("__teste__");
  } catch (e) {
    suporteLocalStorage = false;
  }

  function getStorage(key) {
    return suporteLocalStorage ? localStorage.getItem(key) : document.body.dataset[key] || null;
  }

  function setStorage(key, value) {
    if (suporteLocalStorage) localStorage.setItem(key, value);
    else document.body.dataset[key] = value;
  }

  function aplicarDarkMode(estado) {
    const dark = estado === "enabled";
    document.body.classList.toggle("dark-mode", dark);
    if (toggle) toggle.src = dark ? "icones/sol.png" : "icones/lua.png";
    if (carrinho) carrinho.src = dark ? "icones/carrinho2.png" : "icones/carrinho1.png";
    if (user) user.src = dark ? "icones/user2.png" : "icones/user1.png";
    if (cadeado) cadeado.src = dark ? "icones/cadeado2.png" : "icones/cadeado1.png";

    // Atualiza todos os ícones de logout
    for (let i = 0; i < logouts.length; i++) {
      logouts[i].src = dark ? "icones/logout2.png" : "icones/logout1.png";
    }
  }

  const darkMode = getStorage("darkMode") || "disabled";
  aplicarDarkMode(darkMode);

  if (toggle) {
    toggle.addEventListener("click", () => {
      const novoEstado = document.body.classList.contains("dark-mode") ? "disabled" : "enabled";
      setStorage("darkMode", novoEstado);
      aplicarDarkMode(novoEstado);
    });
  }
});
