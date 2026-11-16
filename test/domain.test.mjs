import test from "node:test";
import assert from "node:assert/strict";
import {
  createDemoEvent,
  searchAnimals,
  toLocalDateInput,
} from "../prototype/domain.mjs";

const animals = [
  {
    id: "cow-20",
    tag: "#20 White",
    former: "#8 White",
    kind: "Cow",
    status: "Review current status",
    note: "Former tag needs verification.",
  },
  {
    id: "calf-1",
    tag: "Unassigned calf",
    former: "No tag recorded",
    kind: "Calf",
    status: "Needs identification",
    note: "Brown calf with white face.",
  },
];

test("search matches a current tag without case sensitivity", () => {
  assert.deepEqual(searchAnimals(animals, "#20 WHITE").map((animal) => animal.id), ["cow-20"]);
});

test("search matches former tags", () => {
  assert.deepEqual(searchAnimals(animals, "#8 white").map((animal) => animal.id), ["cow-20"]);
});

test("search matches temporary animal descriptions", () => {
  assert.deepEqual(searchAnimals(animals, "white face").map((animal) => animal.id), ["calf-1"]);
});

test("empty search returns a copy of all records", () => {
  const results = searchAnimals(animals, "  ");
  assert.deepEqual(results, animals);
  assert.notEqual(results, animals);
});

test("event creation keeps approximate dates and trims notes", () => {
  const record = createDemoEvent({
    type: "Birth",
    animalId: "cow-20",
    animalTag: "#20 White",
    date: "2026-11-16",
    notes: "  New calf observed  ",
    approximate: true,
  });

  assert.equal(record.animalId, "cow-20");
  assert.equal(record.text, "#20 White — New calf observed");
  assert.equal(record.meta, "2026-11-16 • approximate • local demo session");
});

test("event creation rejects missing and impossible dates", () => {
  assert.throws(() => createDemoEvent({ type: "Birth", date: "" }), /valid event date/);
  assert.throws(() => createDemoEvent({ type: "Birth", date: "2026-02-30" }), /valid event date/);
});

test("local date formatting uses the supplied local date", () => {
  const date = new Date(2026, 10, 16, 12, 0, 0);
  assert.equal(toLocalDateInput(date), "2026-11-16");
});
