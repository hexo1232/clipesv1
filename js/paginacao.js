// ============================================================
// 🔹 PAGINAÇÃO CLIENT-SIDE DOS CARDS
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  const cards = Array.from(document.querySelectorAll(".cards-container .card"));
  const pagination = document.getElementById("pagination");
  const cardsPerPage = 9;
  const totalPages = Math.ceil(cards.length / cardsPerPage);
  let currentPage = 1;

  function showPage(page) {
    currentPage = page;
    const start = (page - 1) * cardsPerPage;
    const end = start + cardsPerPage;

    // Oculta todos os cards
    cards.forEach((card, index) => {
      card.style.display = (index >= start && index < end) ? "block" : "none";
    });

    // Atualiza botões de paginação
    renderPagination();
  }

  function renderPagination() {
    pagination.innerHTML = "";

    // Botão "Primeira"
    const firstBtn = document.createElement("button");
    firstBtn.textContent = "« Primeira";
    firstBtn.disabled = currentPage === 1;
    firstBtn.onclick = () => showPage(1);
    pagination.appendChild(firstBtn);

    // Botões numéricos
    for (let i = 1; i <= totalPages; i++) {
      const btn = document.createElement("button");
      btn.textContent = i;
      if (i === currentPage) btn.classList.add("active");
      btn.onclick = () => showPage(i);
      pagination.appendChild(btn);
    }

    // Botão "Última"
    const lastBtn = document.createElement("button");
    lastBtn.textContent = "Última »";
    lastBtn.disabled = currentPage === totalPages;
    lastBtn.onclick = () => showPage(totalPages);
    pagination.appendChild(lastBtn);
  }

  // Inicializa a primeira página
  if (cards.length > 0) showPage(1);
});
