document.addEventListener("DOMContentLoaded", () => {
  const botoesOlho = document.querySelectorAll(".toggle-senha");

  // Verifica se o modo escuro está ativo
  function estaDarkMode() {
    return document.body.classList.contains("dark-mode");
  }

  // Atualiza o ícone do olho de acordo com o estado e o tema
  function atualizarIcone(botao, visivel) {
    const dark = estaDarkMode();
    if (visivel) {
      // Senha visível → olho aberto
      botao.src = dark
        ? "icones/olho_aberto2.png"  // modo escuro
        : "icones/olho_aberto1.png"; // modo claro
    } else {
      // Senha oculta → olho fechado
      botao.src = dark
        ? "icones/olho_fechado2.png" // modo escuro
        : "icones/olho_fechado1.png";// modo claro
    }
  }

  // Função que liga cada botão ao seu input (usando classes)
  botoesOlho.forEach((botao) => {
    const targetClass = botao.dataset.target;
    const input = document.querySelector(`.${targetClass}`);

    if (!input) return;

    // Define o ícone inicial com base no modo atual
    atualizarIcone(botao, false);

    // Alternar a visibilidade da senha ao clicar
    botao.addEventListener("click", () => {
      const visivel = input.type === "text";
      input.type = visivel ? "password" : "text";
      atualizarIcone(botao, !visivel);
    });
  });

  // Observa mudanças na classe do body (para atualizar ao trocar de tema)
  const observer = new MutationObserver(() => {
    botoesOlho.forEach((botao) => {
      const targetClass = botao.dataset.target;
      const input = document.querySelector(`.${targetClass}`);
      if (input) {
        const visivel = input.type === "text";
        atualizarIcone(botao, visivel);
      }
    });
  });

  observer.observe(document.body, { attributes: true, attributeFilter: ["class"] });
});
