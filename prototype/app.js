import { createDemoEvent, searchAnimals, toLocalDateInput } from "./domain.mjs";

var animals = [
  { id: "animal-020", tag: "#20 White", former: "#8 White", kind: "Cow", status: "Review current status", note: "Source notes say this cow was formerly #8 on the white side." },
  { id: "animal-014", tag: "#14 Handwritten Yellow", former: "Earlier handwritten tag", kind: "Cow", status: "Review tag history", note: "The notes refer to a handwritten tag and a later yellow tag." },
  { id: "animal-033", tag: "#33 White", former: "#47 Yellow", kind: "Cow", status: "Review current status", note: "Source notes say #33 was #47. Confirm the current identity." },
  { id: "animal-096", tag: "#96 Yellow", former: "#9", kind: "Cow", status: "Review tag history", note: "The November roster includes this former-tag note." },
  { id: "animal-calf-01", tag: "Unassigned calf", former: "No tag recorded", kind: "Calf", status: "Needs identification", note: "Use a temporary description until the calf is identified and tagged." },
  { id: "animal-1347", tag: "#1347", former: "Herd bull", kind: "Bull", status: "Historical: died", note: "The source notes record this herd bull as deceased. Kept here as a historical example." }
];

var events = [
  { type: "Tag history", text: "#20 White was formerly #8 White", meta: "Source note • date not recorded", time: "Identity" },
  { type: "Birth note", text: "Calf reported for #3", meta: "Week of March 15, 2025 • approximate", time: "Birth" },
  { type: "Herd update", text: "New calf reported for #14", meta: "July 13, 2026 • source note", time: "Birth" }
];

var navLabels = {
  overview: "Overview",
  herd: "Herd",
  calendar: "Calendar",
  grazing: "Grazing",
  operations: "Operations"
};
var activeView = "overview";
var toastTimer;

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, function (char) {
    return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[char];
  });
}

function setView(view) {
  activeView = view;
  document.getElementById("crumb").textContent = navLabels[view];
  document.querySelectorAll("[data-view]").forEach(function (button) {
    button.classList.toggle("active", button.getAttribute("data-view") === view);
  });
  render();
}

function localDate() {
  return toLocalDateInput();
}

function openEvent(type) {
  var dialog = document.getElementById("eventDialog");
  var select = document.getElementById("eventType");
  select.value = type || "Note";
  document.getElementById("dialogTitle").textContent = (type || "Note") === "Note" ? "Add a quick record" : "Record " + (type || "an event").toLowerCase();
  document.getElementById("eventDate").value = localDate();
  document.getElementById("eventAnimal").innerHTML = '<option value="Herd group">Herd group</option>' + animals.map(function (animal) {
    return '<option value="' + escapeHtml(animal.id) + '">' + escapeHtml(animal.tag) + "</option>";
  }).join("");
  dialog.showModal();
}

function closeDialog() {
  document.getElementById("eventDialog").close();
  var animalDialog = document.getElementById("animalDialog");
  if (animalDialog.open) animalDialog.close();
}

function showToast(message) {
  var toast = document.getElementById("toast");
  toast.textContent = message;
  toast.classList.add("show");
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(function () { toast.classList.remove("show"); }, 2600);
}

function animalRow(animal) {
  var initials = animal.tag.replace("#", "").slice(0, 2).toUpperCase();
  return '<tr>' +
    '<td><button class="animal-link" data-action="open-animal" data-id="' + escapeHtml(animal.id) + '"><span class="animal-token">' + escapeHtml(initials) + '</span><span><strong>' + escapeHtml(animal.tag) + '</strong><small>' + escapeHtml(animal.former) + '</small></span></button></td>' +
    '<td class="type-label">' + escapeHtml(animal.kind) + '</td>' +
    '<td><span class="status-pill review">' + escapeHtml(animal.status) + '</span></td>' +
    '<td><button class="text-button" data-action="open-animal" data-id="' + escapeHtml(animal.id) + '">View record&nbsp; ›</button></td>' +
    "</tr>";
}

function activityRows() {
  return events.slice(0, 4).map(function (event) {
    return '<div class="activity-row"><span class="activity-icon">↗</span><div><p class="activity-text"><strong>' + escapeHtml(event.type) + ":</strong> " + escapeHtml(event.text) + '</p><p class="activity-meta">' + escapeHtml(event.meta) + '</p></div><span class="activity-time">' + escapeHtml(event.time) + "</span></div>";
  }).join("");
}

function renderOverview() {
  return '<div class="stats-grid">' +
    '<article class="stat-card"><div class="stat-top"><span>Animals in sample</span><span class="stat-icon">♧</span></div><div class="stat-value">58</div><span class="stat-note">Illustrative count only</span></article>' +
    '<article class="stat-card"><div class="stat-top"><span>Needs review</span><span class="stat-icon" style="background:#f8f0df;color:#b98135">!</span></div><div class="stat-value">6</div><span class="stat-note">Unconfirmed sample records</span></article>' +
    '<article class="stat-card"><div class="stat-top"><span>Paddocks</span><span class="stat-icon">⌁</span></div><div class="stat-value">8</div><span class="stat-note">Placeholder names</span></article>' +
    '<article class="stat-card"><div class="stat-top"><span>Events this month</span><span class="stat-icon">▦</span></div><div class="stat-value">12</div><span class="stat-note">Sample timeline</span></article>' +
    '</div>' +
    '<div class="dashboard-grid"><div>' +
      '<section class="panel"><div class="panel-header"><div><h2>Needs a look</h2><p>Review prompts from the sample records</p></div><button class="text-button" data-view="herd">Open herd&nbsp; ›</button></div>' +
        '<div class="task-list">' +
          '<div class="task-card"><span class="task-mark">↺</span><div><p class="task-title">Confirm animal identity</p><p class="task-meta">#20 White has a former-tag note for #8 White</p></div><span class="status-pill review">Review</span></div>' +
          '<div class="task-card"><span class="task-mark red">?</span><div><p class="task-title">Unassigned calf</p><p class="task-meta">Temporary description; mother not confirmed</p></div><span class="status-pill review">Identify</span></div>' +
          '<div class="task-card"><span class="task-mark green">⌁</span><div><p class="task-title">Paddock move log</p><p class="task-meta">Record the next move when it happens</p></div><button class="text-button" data-action="open-event" data-type="Paddock move">Record&nbsp; ›</button></div>' +
        '</div></section>' +
      '<section class="panel"><div class="panel-header"><div><h2>Recent activity</h2><p>Events and notes, newest first</p></div><button class="text-button" data-view="calendar">View timeline&nbsp; ›</button></div><div class="activity-list">' + activityRows() + '</div></section>' +
    '</div><div>' +
      '<section class="panel"><div class="panel-header"><div><h2>Grazing snapshot</h2><p>Sample movement log • not a recommendation</p></div><button class="text-button" data-view="grazing">All paddocks&nbsp; ›</button></div>' +
        '<div class="paddock-list">' +
          '<div class="paddock-row"><span class="paddock-num">01</span><div><p class="paddock-name">Paddock 1</p><p class="paddock-meta">Group A • move date not confirmed</p></div><span class="mini-track"><span style="width:35%"></span></span></div>' +
          '<div class="paddock-row"><span class="paddock-num">02</span><div><p class="paddock-name">Paddock 2</p><p class="paddock-meta">Group A • placeholder</p></div><span class="mini-track"><span style="width:62%"></span></span></div>' +
          '<div class="paddock-row"><span class="paddock-num">03</span><div><p class="paddock-name">Paddock 3</p><p class="paddock-meta">Rest days not configured</p></div><span class="mini-track"><span style="width:18%"></span></span></div>' +
        '</div><div class="callout">Record actual moves first. Grazing alerts need client-approved timing and forage targets.</div></section>' +
      '<section class="panel"><div class="panel-header"><div><h2>Next on the calendar</h2><p>Sample dates for layout review</p></div><button class="text-button" data-view="calendar">Open calendar&nbsp; ›</button></div>' +
        '<div class="schedule-list">' +
          '<div class="schedule-row"><div class="date-box"><strong>21</strong><small>Nov</small></div><div><h3>Projected calving window</h3><p>Demo record • confirm rule first</p></div><span class="status-pill review">Estimate</span></div>' +
          '<div class="schedule-row"><div class="date-box"><strong>04</strong><small>Dec</small></div><div><h3>Butcher booking</h3><p>Sample appointment • not client data</p></div><span class="status-pill">Plan</span></div>' +
        '</div></section>' +
    '</div></div>';
}

function renderHerd() {
  return '<section class="panel"><div class="section-heading"><div><h2>Herd roster</h2><p>Search current or former tags. Every row is a sample needing verification.</p></div><div class="search-box"><span>⌕</span><input id="herdSearch" type="search" placeholder="Search tag or description" aria-label="Search herd"></div></div>' +
    '<div class="table-wrap"><table class="herd-table"><thead><tr><th>Animal / tag history</th><th>Type</th><th>Review status</th><th></th></tr></thead><tbody id="herdRows">' + animals.map(animalRow).join("") + '</tbody></table></div>' +
    '<div class="callout">Tag numbers can change or be reused. Search the visible history, and confirm uncertain matches before importing them.</div></section>';
}

function renderCalendar() {
  return '<section class="panel"><div class="panel-header"><div><h2>Calendar and follow-ups</h2><p>Use this view to review projected dates separately from confirmed events.</p></div><button class="secondary-button" data-action="open-event" data-type="Birth">＋ Record birth</button></div>' +
    '<div class="callout">Provisional compatibility rule: the source sheet adds 45 days, then nine calendar months. Dates below are layout examples only. The client needs to confirm the rule before use.</div>' +
    '<div class="schedule-list" style="margin-top:14px">' +
      '<div class="schedule-row"><div class="date-box"><strong>21</strong><small>Nov</small></div><div><h3>Calving estimate</h3><p>Demo cow • projected window, not a confirmed date</p></div><span class="status-pill review">Estimate</span></div>' +
      '<div class="schedule-row"><div class="date-box"><strong>28</strong><small>Nov</small></div><div><h3>Breeding review</h3><p>Demo cow • check actual history before acting</p></div><span class="status-pill review">Review</span></div>' +
      '<div class="schedule-row"><div class="date-box"><strong>04</strong><small>Dec</small></div><div><h3>Processor booking</h3><p>Example booking • confirm processor and animal</p></div><span class="status-pill">Plan</span></div>' +
    '</div></section>';
}

function renderGrazing() {
  var cards = [];
  for (var i = 1; i <= 8; i++) {
    cards.push('<article class="paddock-card"><div class="paddock-card-top"><div><h3>Paddock ' + i + '</h3><p>Editable placeholder name</p></div><span class="paddock-num">' + String(i).padStart(2, "0") + '</span></div><div class="paddock-detail"><span>Group A</span><span>Move date not set</span></div><button class="outline-button" data-action="open-event" data-type="Paddock move">＋ Record move</button></article>');
  }
  return '<div class="section-heading"><div><h2>Rotational grazing</h2><p>Eight editable paddock placeholders. This screen records moves; it does not prescribe them.</p></div><button class="primary-button" data-action="open-event" data-type="Paddock move"><span>＋</span> Record move</button></div>' +
    '<div class="paddock-grid">' + cards.join("") + '</div><div class="callout">Add the client’s paddock names, groups, and target rest/graze ranges before showing any “move due” alert.</div>';
}

function renderOperations() {
  return '<div class="operation-grid">' +
    '<article class="operation-card"><span class="stat-icon">▤</span><h3>Feedlot and finishing</h3><p>Log feed buckets, bucket weight, recipe ingredients, weigh-ins, and purchase costs. Only calculate cost when the quantities and units are confirmed.</p><button class="secondary-button" data-action="open-event" data-type="Weight">＋ Log weight or feed</button></article>' +
    '<article class="operation-card"><span class="stat-icon">▦</span><h3>Butcher schedule</h3><p>Keep planned drop-off dates and processor details together. The demo does not contain real bookings.</p><button class="secondary-button" data-action="open-event" data-type="Note">＋ Add booking note</button></article>' +
    '<article class="operation-card"><span class="stat-icon">◈</span><h3>Health records</h3><p>Record what was actually given, when, and by whom. Do not preload a medical schedule without the client’s veterinarian.</p><button class="secondary-button" data-action="open-event" data-type="Treatment">＋ Record treatment</button></article>' +
    '<article class="operation-card"><span class="stat-icon">⌂</span><h3>Costs and sales</h3><p>Track expenses and sale records with their source and units. Finance visibility can be restricted by role in a production version.</p><button class="secondary-button" data-action="open-event" data-type="Note">＋ Add expense note</button></article>' +
  '</div><div class="callout">Operations are the next slice after the verified herd record and event workflow.</div>';
}

function render() {
  var content = document.getElementById("page");
  var title = document.getElementById("pageTitle");
  var subtitle = document.getElementById("pageSubtitle");
  document.getElementById("crumb").textContent = navLabels[activeView];
  if (activeView === "overview") {
    title.textContent = "Good morning";
    subtitle.textContent = "A clear view of the herd, today’s records, and what needs a look.";
    content.innerHTML = renderOverview();
  } else if (activeView === "herd") {
    title.textContent = "Herd";
    subtitle.textContent = "Find an animal by its current or former tag.";
    content.innerHTML = renderHerd();
    var search = document.getElementById("herdSearch");
    search.addEventListener("input", function () {
      var query = search.value.toLowerCase();
      document.getElementById("herdRows").innerHTML = searchAnimals(animals, query).map(animalRow).join("") || '<tr><td colspan="4" class="empty-state">No matching sample records.</td></tr>';
    });
  } else if (activeView === "calendar") {
    title.textContent = "Calendar";
    subtitle.textContent = "Separate estimated dates from confirmed events.";
    content.innerHTML = renderCalendar();
  } else if (activeView === "grazing") {
    title.textContent = "Grazing";
    subtitle.textContent = "Keep paddock names and move history in one place.";
    content.innerHTML = renderGrazing();
  } else {
    title.textContent = "Operations";
    subtitle.textContent = "Feed, health, processor, and cost records.";
    content.innerHTML = renderOperations();
  }
}

function openAnimal(id) {
  var animal = animals.find(function (item) { return item.id === id; });
  if (!animal) return;
  var dialog = document.getElementById("animalDialog");
  dialog.innerHTML = '<div class="dialog-head"><div><p class="profile-kicker">SAMPLE ANIMAL RECORD</p><h2>' + escapeHtml(animal.tag) + '</h2></div><button class="close-button" type="button" data-action="close-dialog" aria-label="Close">×</button></div>' +
    '<p style="margin:0;color:#78847b;font-size:10px">' + escapeHtml(animal.kind) + ' • ' + escapeHtml(animal.note) + '</p>' +
    '<div class="profile-tags"><span class="tag-chip">' + escapeHtml(animal.tag) + '</span><span class="tag-chip old">' + escapeHtml(animal.former) + '</span></div>' +
    '<div class="profile-summary"><div><small>Review status</small><strong>' + escapeHtml(animal.status) + '</strong></div><div><small>Internal demo ID</small><strong>' + escapeHtml(animal.id) + '</strong></div></div>' +
    '<div class="panel-header"><div><h2>Identity timeline</h2><p>Historical tags are retained instead of overwritten.</p></div></div>' +
    '<div class="timeline"><div class="timeline-row"><span class="timeline-date">Date unknown</span><span class="timeline-marker"></span><div class="timeline-content"><strong>Current tag note</strong><p>' + escapeHtml(animal.tag) + ' • source record needs client verification</p></div></div><div class="timeline-row"><span class="timeline-date">Earlier record</span><span class="timeline-marker"></span><div class="timeline-content"><strong>Former identifier</strong><p>' + escapeHtml(animal.former) + ' • preserved as a searchable alias</p></div></div></div>' +
    '<div class="callout">Illustrative only. Confirm animal identity, dates, and status before this data is used.</div>';
  dialog.showModal();
}

document.addEventListener("click", function (event) {
  var viewButton = event.target.closest("[data-view]");
  if (viewButton) { setView(viewButton.getAttribute("data-view")); return; }
  var actionButton = event.target.closest("[data-action]");
  if (!actionButton) return;
  var action = actionButton.getAttribute("data-action");
  if (action === "open-event") openEvent(actionButton.getAttribute("data-type"));
  else if (action === "close-dialog") closeDialog();
  else if (action === "open-animal") openAnimal(actionButton.getAttribute("data-id"));
});

document.getElementById("eventForm").addEventListener("submit", function (event) {
  event.preventDefault();
  var form = new FormData(event.currentTarget);
  var animalId = String(form.get("animal"));
  var animal = animals.find(function (item) { return item.id === animalId; });
  var type = String(form.get("type"));
  var date = String(form.get("date"));
  var notes = String(form.get("notes") || "").trim();
  var who = animal ? animal.tag : "Herd group";
  var record = createDemoEvent({
    type: type,
    animalId: animalId,
    animalTag: who,
    date: date,
    notes: notes,
    approximate: Boolean(form.get("approximate"))
  });
  events.unshift(record);
  document.getElementById("eventDialog").close();
  render();
  showToast("Record added to this demo session.");
});

document.querySelector(".banner-close").addEventListener("click", function () {
  document.querySelector(".demo-banner").remove();
});

render();
