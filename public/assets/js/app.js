// Formulário de receita: adicionar e remover linhas de ingrediente
const items = document.getElementById("items");
const addButton = document.getElementById("add-item");

if (items && addButton) {
  addButton.addEventListener("click", () => {
    const row = items.querySelector(".item-row").cloneNode(true);
    row.querySelector("select").value = "";
    row.querySelector("input").value = "";
    items.append(row);
    row.querySelector("select").focus();
  });

  items.addEventListener("click", (event) => {
    if (!event.target.matches("[data-remove]")) return;
    const rows = items.querySelectorAll(".item-row");
    if (rows.length > 1) event.target.closest(".item-row").remove();
    else rows[0].querySelectorAll("select, input").forEach((field) => (field.value = ""));
  });
}

// Selects que atualizam a página ao mudar (sem JavaScript inline, por causa da CSP)
document.querySelectorAll("select[data-autosubmit]").forEach((select) => {
  select.addEventListener("change", () => select.form.submit());
});
