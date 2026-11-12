document.addEventListener('DOMContentLoaded', () => {
  const title = document.querySelector('[name="title"]');
  const description = document.querySelector('[name="description"]');
  const category = document.querySelector('[name="category"]');
  const priority = document.querySelector('#priority');
  const hint = document.querySelector('#priority-hint');
  if (!title || !description || !category || !priority || !hint) return;
  const update = () => {
    const text = `${title.value} ${description.value}`.toLowerCase();
    const urgent = /\b(urgent|emergency|security breach|data breach|system down|cannot access|locked out|safety risk)\b/.test(text);
    const routine = category.value === 'General Administration' && /\b(information|question|where|when|guidance)\b/.test(text);
    const recommended = urgent ? 'high' : (routine ? 'low' : 'normal');
    hint.textContent = `Live suggestion: ${recommended}. The server recalculates this rule when submitted. High priority targets 2 days, normal 5 days, low 8 days. You choose the final priority.`;
  };
  title.addEventListener('input', update);
  description.addEventListener('input', update);
  category.addEventListener('change', update);
});
