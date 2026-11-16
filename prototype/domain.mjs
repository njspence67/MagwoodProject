export function toLocalDateInput(date = new Date()) {
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return date.getFullYear() + "-" + month + "-" + day;
}

export function searchAnimals(animals, query = "") {
  const normalizedQuery = String(query).trim().toLocaleLowerCase();
  if (!normalizedQuery) return animals.slice();

  return animals.filter((animal) => {
    const searchable = [
      animal.tag,
      animal.former,
      animal.kind,
      animal.status,
      animal.note,
    ].filter(Boolean).join(" ").toLocaleLowerCase();

    return searchable.includes(normalizedQuery);
  });
}

function isValidDateInput(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const [year, month, day] = value.split("-").map(Number);
  const date = new Date(year, month - 1, day);
  return date.getFullYear() === year
    && date.getMonth() === month - 1
    && date.getDate() === day;
}

export function createDemoEvent({
  type,
  animalId,
  animalTag,
  date,
  notes = "",
  approximate = false,
}) {
  const cleanType = String(type || "").trim();
  const cleanDate = String(date || "").trim();
  const cleanTag = String(animalTag || "Herd group").trim();
  const cleanNotes = String(notes || "").trim();

  if (!cleanType) throw new Error("Event type is required.");
  if (!isValidDateInput(cleanDate)) throw new Error("A valid event date is required.");

  const fallbackNote = cleanType === "Paddock move"
    ? "Move logged; add paddock details when confirmed."
    : "Record added in the demo.";

  return {
    type: cleanType,
    animalId: String(animalId || "Herd group"),
    text: cleanTag + " — " + (cleanNotes || fallbackNote),
    meta: cleanDate + (approximate ? " • approximate" : "") + " • local demo session",
    time: "Just now",
  };
}
