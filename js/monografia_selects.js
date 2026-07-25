// =============================
// Função: Carregar Cursos por Divisão
// =============================
function carregarCursos(selected = null) {
  const id_divisao = document.getElementById("divisao").value;
  const cursoSelect = document.getElementById("curso");
  const areaSelect = document.getElementById("area_pesquisa");

  cursoSelect.innerHTML = '<option value="">Carregando cursos...</option>';
  areaSelect.innerHTML = '<option value="">Selecione a área de pesquisa</option>';

  if (!id_divisao) {
    cursoSelect.innerHTML = '<option value="">Selecione uma divisão primeiro</option>';
    areaSelect.innerHTML = '<option value="">Selecione uma área de pesquisa</option>';
    return;
  }

  fetch(`?ajax=cursos&divisao=${id_divisao}`)
    .then(r => r.text())
    .then(t => {
      cursoSelect.innerHTML = t.trim();
      if (selected) cursoSelect.value = selected;
      else cursoSelect.selectedIndex = 0;
    })
    .catch(err => {
      console.error("Erro ao carregar cursos:", err);
      cursoSelect.innerHTML = '<option value="">Erro ao carregar cursos</option>';
    });
}


// =============================
// Função: Carregar Áreas de Pesquisa por Curso
// =============================
function carregarAreasPesquisa(selected = null) {
  const id_curso = document.getElementById("curso").value;
  const areaSelect = document.getElementById("area_pesquisa");

  areaSelect.innerHTML = '<option value="">Carregando áreas...</option>';

  if (!id_curso) {
    areaSelect.innerHTML = '<option value="">Selecione um curso primeiro</option>';
    return;
  }

  fetch(`?ajax=areas_pesquisa&curso=${id_curso}`)
    .then(r => r.text())
    .then(t => {
      areaSelect.innerHTML = t.trim();
      if (selected) areaSelect.value = selected;
      else areaSelect.selectedIndex = 0;
    })
    .catch(err => {
      console.error("Erro ao carregar áreas:", err);
      areaSelect.innerHTML = '<option value="">Erro ao carregar áreas</option>';
    });
}


// =============================
// Função: Dropzone de Upload PDF
// =============================
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('monografia_file');
const fileNameDisplay = document.getElementById('fileName');

if (dropZone && fileInput && fileNameDisplay) {
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt =>
    dropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); })
  );
  ['dragenter', 'dragover'].forEach(evt =>
    dropZone.addEventListener(evt, () => dropZone.classList.add('drag-over'))
  );
  ['dragleave', 'drop'].forEach(evt =>
    dropZone.addEventListener(evt, () => dropZone.classList.remove('drag-over'))
  );

  dropZone.addEventListener('drop', e => {
    const f = e.dataTransfer.files[0];
    if (f && f.type === 'application/pdf') {
      fileInput.files = e.dataTransfer.files;
      fileNameDisplay.textContent = `Selecionado: ${f.name}`;
    } else {
      alert("❌ Apenas arquivos PDF são aceitos.");
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
      const f = fileInput.files[0];
      fileNameDisplay.textContent = f.name;
    }
  });
}


// =============================
// Carregar selects ao abrir página (modo edição)
// =============================
window.addEventListener("DOMContentLoaded", () => {
  const divisao = document.getElementById("divisao")?.value;
  const curso = window.preSelectedCurso || null;
  const area = window.preSelectedArea || null;

  if (!divisao) return;

  // Carregar cursos correspondentes
  fetch(`?ajax=cursos&divisao=${divisao}`)
    .then(r => r.text())
    .then(html => {
      const cursoSelect = document.getElementById("curso");
      cursoSelect.innerHTML = html.trim();
      if (curso) cursoSelect.value = curso;

      // Carregar áreas correspondentes ao curso selecionado
      if (curso) {
        fetch(`?ajax=areas_pesquisa&curso=${curso}`)
          .then(r => r.text())
          .then(html2 => {
            const areaSelect = document.getElementById("area_pesquisa");
            areaSelect.innerHTML = html2.trim();
            if (area) areaSelect.value = area;
          });
      }
    })
    .catch(err => console.error("Erro ao carregar cursos/áreas:", err));
});


// =============================
// Eventos de atualização dinâmica
// =============================
document.getElementById("divisao")?.addEventListener("change", () => carregarCursos());
document.getElementById("curso")?.addEventListener("change", () => carregarAreasPesquisa());
