document.addEventListener('DOMContentLoaded', () => {
  const title = document.querySelector('[name="title"]');
  const description = document.querySelector('[name="description"]');
  const priority = document.querySelector('#priority');
  const hint = document.querySelector('#priority-hint');
  if (!title || !description || !priority || !hint) return;
  const update = () => {
    const text = `${title.value} ${description.value}`.toLowerCase();
    const urgent = /\b(urgent|emergency|security breach|cannot access|system down|locked out)\b/.test(text);
    const recommended = urgent ? 'high' : 'normal';
    hint.textContent = `Transparent estimate: high priority 2 days, normal 5 days, low 8 days. Rule-based suggestion: ${recommended} (urgent access, outage or security terms trigger high). You can choose another priority.`;
  };
  title.addEventListener('input', update);
  description.addEventListener('input', update);
});
