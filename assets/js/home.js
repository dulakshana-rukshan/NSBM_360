document.addEventListener("DOMContentLoaded", function () {
  // ==========================================
  // DAILY WELCOME BACKGROUND IMAGE
  // ==========================================
  const welcomeSection = document.querySelector(".welcome-section");
  const welcomeImages = [
    "/NSBM_360/assets/image/welcome-1.jpg",
    "/NSBM_360/assets/image/welcome-2.jpg",
    "/NSBM_360/assets/image/welcome-3.jpg",
    "/NSBM_360/assets/image/welcome-4.jpg",
    "/NSBM_360/assets/image/welcome-5.jpg",
    "/NSBM_360/assets/image/welcome-6.jpg",
    "/NSBM_360/assets/image/welcome-7.jpg",
  ];

  function updateWelcomeBackground() {
    if (!welcomeSection) return;
    const imageIndex = (new Date().getDay() + 6) % 7;
    const image = welcomeImages[imageIndex];
    welcomeSection.style.setProperty("--welcome-bg", `url("${image}")`);
  }

  updateWelcomeBackground();
  setInterval(updateWelcomeBackground, 60000);

  // =========================
  // LATEST NEWS + LIVE AUTO REFRESH
  // =========================
  const newsSlider = document.getElementById("newsSlider");
  if (newsSlider) {
    let current = 0;
    let autoSlide = null;
    let isLoading = false;
    let lastSignature = null;

    function getSlides() {
      return [...newsSlider.querySelectorAll(".news-slide")];
    }
    function getDots() {
      return [...newsSlider.querySelectorAll(".news-dot")];
    }
    function showNews(index) {
      const slides = getSlides();
      if (!slides.length) return;
      current = (index + slides.length) % slides.length;
      slides.forEach((slide, i) =>
        slide.classList.toggle("active", i === current),
      );
      getDots().forEach((dot, i) =>
        dot.classList.toggle("active", i === current),
      );
    }
    function stopAutoSlide() {
      clearInterval(autoSlide);
      autoSlide = null;
    }
    function startAutoSlide() {
      stopAutoSlide();
      if (getSlides().length > 1)
        autoSlide = setInterval(() => showNews(current + 1), 5000);
    }
    newsSlider.addEventListener("click", (event) => {
      if (event.target.closest(".news-prev")) showNews(current - 1);
      else if (event.target.closest(".news-next")) showNews(current + 1);
      else {
        const dot = event.target.closest(".news-dot");
        if (!dot) return;
        showNews(getDots().indexOf(dot));
      }
      startAutoSlide();
    });
    newsSlider.addEventListener("mouseenter", stopAutoSlide);
    newsSlider.addEventListener("mouseleave", startAutoSlide);
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) stopAutoSlide();
      else {
        startAutoSlide();
        refreshNews();
      }
    });

    function makeElement(tag, className, text) {
      const element = document.createElement(tag);
      if (className) element.className = className;
      if (text !== undefined) element.textContent = text;
      return element;
    }
    function renderNews(items) {
      const selected = getSlides()[current]?.dataset.postId;
      const fragment = document.createDocumentFragment();
      if (!items.length) {
        fragment.appendChild(
          makeElement(
            "div",
            "home-empty-state",
            "No published news available yet.",
          ),
        );
      } else {
        items.forEach((item, index) => {
          const slide = makeElement(
            "article",
            "news-slide" + (index === 0 ? " active" : ""),
          );
          slide.dataset.postId = String(item.post_id);
          const image = makeElement("div", "news-slide-image");
          image.style.backgroundImage = `url(${JSON.stringify(item.cover_image)})`;
          const overlay = makeElement("div", "news-slide-overlay");
          overlay.appendChild(
            makeElement("span", "news-category", item.category_name),
          );
          overlay.appendChild(makeElement("h3", "", item.title));
          overlay.appendChild(
            makeElement(
              "p",
              "",
              item.summary || "Read the latest campus update.",
            ),
          );
          overlay.appendChild(
            makeElement("span", "news-date", item.date_label),
          );
          slide.append(image, overlay);
          fragment.appendChild(slide);
        });
        if (items.length > 1) {
          const prev = makeElement("button", "news-arrow news-prev");
          prev.type = "button";
          prev.setAttribute("aria-label", "Previous news");
          prev.appendChild(makeElement("i", "bi bi-chevron-left"));
          const next = makeElement("button", "news-arrow news-next");
          next.type = "button";
          next.setAttribute("aria-label", "Next news");
          next.appendChild(makeElement("i", "bi bi-chevron-right"));
          const dots = makeElement("div", "news-dots");
          items.forEach((_, index) => {
            const dot = makeElement(
              "button",
              "news-dot" + (index === 0 ? " active" : ""),
            );
            dot.type = "button";
            dot.setAttribute("aria-label", `News slide ${index + 1}`);
            dots.appendChild(dot);
          });
          fragment.append(prev, next, dots);
        }
      }
      stopAutoSlide();
      newsSlider.replaceChildren(fragment);
      const preservedIndex = items.findIndex(
        (item) => String(item.post_id) === selected,
      );
      current = preservedIndex >= 0 ? preservedIndex : 0;
      showNews(current);
      if (!newsSlider.matches(":hover") && !document.hidden) startAutoSlide();
    }

    async function refreshNews() {
      if (isLoading || document.hidden) return;
      isLoading = true;
      try {
        const response = await fetch("/NSBM_360/api/latest_news.php", {
          cache: "no-store",
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (!Array.isArray(data)) throw new Error("Invalid news response");
        const signature = JSON.stringify(data);
        if (signature !== lastSignature) {
          lastSignature = signature;
          renderNews(data);
        }
      } catch (error) {
        console.warn(
          "Live news refresh failed; existing news remains visible.",
          error,
        );
      } finally {
        isLoading = false;
      }
    }
    showNews(0);
    startAutoSlide();
    refreshNews();
    setInterval(refreshNews, 30000);
  }

  // =========================
  // CAMPUS ACTIVITY SLIDER
  // =========================
  const activitySlides = document.querySelectorAll(".activity-slide");
  const activityDots = document.querySelectorAll(".activity-dot");
  const activityPrev = document.getElementById("activityPrev");
  const activityNext = document.getElementById("activityNext");
  let currentActivity = 0;
  const featuredEventSlide = document.getElementById("featuredEventSlide");
  const upcomingEventsList = document.getElementById("upcomingEventsList");

  // =========================
  // INTERACTIVE CALENDAR
  // =========================
  const calendarMonth = document.getElementById("calendarMonth");
  const calendarDays = document.getElementById("calendarDays");
  const calendarInfo = document.getElementById("calendarEventInfo");
  const calendarPrev = document.getElementById("calendarPrev");
  const calendarNext = document.getElementById("calendarNext");

  // =========================
  // CUSTOM CALENDAR TOOLTIP
  // =========================
  const calendarTooltip = document.createElement("div");
  calendarTooltip.className = "calendar-tooltip";
  calendarTooltip.setAttribute("role", "tooltip");
  calendarTooltip.setAttribute("aria-hidden", "true");
  document.body.appendChild(calendarTooltip);

  function hideCalendarTooltip() {
    calendarTooltip.classList.remove("show");
    calendarTooltip.setAttribute("aria-hidden", "true");
  }

  function showActivity(index) {
    if (!activitySlides.length) return;
    currentActivity = (index + activitySlides.length) % activitySlides.length;
    activitySlides.forEach((slide, i) =>
      slide.classList.toggle("active", i === currentActivity),
    );
    activityDots.forEach((dot, i) =>
      dot.classList.toggle("active", i === currentActivity),
    );
    hideCalendarTooltip();
  }
  if (activityPrev && activityNext && activitySlides.length) {
    activityPrev.addEventListener("click", () =>
      showActivity(currentActivity - 1),
    );
    activityNext.addEventListener("click", () =>
      showActivity(currentActivity + 1),
    );
  }

  function showCalendarTooltip(button, dayActivities) {
    if (!dayActivities.length) return;
    calendarTooltip.replaceChildren();
    dayActivities.forEach((activity) => {
      const item = document.createElement("div");
      item.className = "tooltip-item";
      const title = document.createElement("strong");
      title.textContent = activity.title;
      const category = document.createElement("span");
      category.className = "tooltip-category";
      category.textContent = activity.type;
      const details = document.createElement("div");
      details.textContent = `${activity.time} · ${activity.location}`;
      item.appendChild(title);
      item.appendChild(category);
      item.appendChild(details);
      calendarTooltip.appendChild(item);
    });
    calendarTooltip.classList.add("show");
    calendarTooltip.setAttribute("aria-hidden", "false");
    const rect = button.getBoundingClientRect();
    const tooltipWidth = calendarTooltip.offsetWidth;
    const tooltipHeight = calendarTooltip.offsetHeight;
    let left = rect.left + rect.width / 2 - tooltipWidth / 2;
    left = Math.max(10, Math.min(left, window.innerWidth - tooltipWidth - 10));
    let top = rect.top - tooltipHeight - 10;
    if (top < 10) top = rect.bottom + 10;
    top = Math.max(10, Math.min(top, window.innerHeight - tooltipHeight - 10));
    calendarTooltip.style.left = `${left}px`;
    calendarTooltip.style.top = `${top}px`;
  }

  if (!calendarMonth || !calendarDays || !calendarInfo) return;
  let displayedDate = new Date();
  displayedDate.setDate(1);
  const calendarDataNode = document.getElementById("homeCalendarData");
  let activities = [];
  if (calendarDataNode) {
    try {
      const parsed = JSON.parse(calendarDataNode.textContent);
      if (Array.isArray(parsed)) activities = parsed;
    } catch (error) {
      console.error("Unable to load calendar events", error);
    }
  }

  // Update events without rebuilding the page or interrupting the calendar month.
  function eventElement(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = String(text);
    return element;
  }
  function renderUpcoming(items) {
    if (!upcomingEventsList) return;
    const fragment = document.createDocumentFragment();
    if (!items.length) {
      fragment.appendChild(
        eventElement(
          "p",
          "upcoming-empty-state",
          "No upcoming events available yet.",
        ),
      );
    }
    items.forEach((item) => {
      const card = eventElement("article", "upcoming-event-card");
      const badge = eventElement("div", "event-date-badge");
      badge.append(
        eventElement("span", "event-date-day", item.date_day),
        eventElement("span", "event-date-month", item.date_month),
      );
      const content = eventElement("div", "upcoming-event-content");
      content.appendChild(
        eventElement("span", "upcoming-event-category", item.category_name),
      );
      content.appendChild(eventElement("h3", "", item.title));
      const meta = eventElement("div", "upcoming-event-meta");
      for (const [icon, value] of [
        ["bi bi-clock", item.time_label],
        ["bi bi-geo-alt", item.venue],
      ]) {
        const span = eventElement("span");
        span.append(
          eventElement("i", icon),
          document.createTextNode(" " + value),
        );
        meta.appendChild(span);
      }
      content.appendChild(meta);
      const imageWrap = eventElement("div", "upcoming-event-image");
      const image = document.createElement("img");
      image.src = item.cover_image;
      image.alt = item.title;
      image.loading = "lazy";
      imageWrap.appendChild(image);
      const link = eventElement("a", "upcoming-event-arrow");
      link.href = "/NSBM_360/pages/events.php";
      link.setAttribute("aria-label", "View " + item.title);
      link.appendChild(eventElement("i", "bi bi-arrow-up-right"));
      card.append(badge, content, imageWrap, link);
      fragment.appendChild(card);
    });
    upcomingEventsList.replaceChildren(fragment);
  }
  function renderFeatured(items) {
    if (!featuredEventSlide) return;
    const fragment = document.createDocumentFragment();
    const item = items[0];
    fragment.appendChild(
      eventElement(
        "span",
        "activity-status",
        item ? "UPCOMING EVENT" : "CAMPUS UPDATES",
      ),
    );
    fragment.appendChild(
      eventElement("h3", "", item ? item.title : "No Upcoming Events"),
    );
    fragment.appendChild(
      eventElement(
        "p",
        "activity-description",
        item
          ? item.summary || "Discover upcoming campus activities."
          : "Check back soon for new campus activities.",
      ),
    );
    if (item) {
      for (const [icon, value] of [
        ["bi bi-calendar-event", item.date_label + " · " + item.time_label],
        ["bi bi-geo-alt", item.venue],
      ]) {
        const detail = eventElement("div", "activity-detail");
        detail.append(eventElement("i", icon), eventElement("span", "", value));
        fragment.appendChild(detail);
      }
    }
    const link = eventElement("a", "activity-view-link", "Explore Events ");
    link.href = "/NSBM_360/pages/events.php";
    link.appendChild(eventElement("i", "bi bi-arrow-right"));
    fragment.appendChild(link);
    featuredEventSlide.replaceChildren(fragment);
  }
  let lastEventSignature = null;
  let eventsLoading = false;
  async function refreshEvents() {
    if (document.hidden || eventsLoading) return;
    eventsLoading = true;
    try {
      const response = await fetch("/NSBM_360/api/latest_events.php", {
        cache: "no-store",
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      if (!Array.isArray(data.upcoming) || !Array.isArray(data.calendar))
        throw new Error("Invalid events response");
      const signature = JSON.stringify(data);
      if (signature !== lastEventSignature) {
        lastEventSignature = signature;
        renderUpcoming(data.upcoming);
        renderFeatured(data.upcoming);
        activities = data.calendar;
        renderCalendar();
      }
    } catch (error) {
      console.warn(
        "Live events refresh failed; existing events remain visible.",
        error,
      );
    } finally {
      eventsLoading = false;
    }
  }
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) refreshEvents();
  });
  setInterval(refreshEvents, 30000);
  refreshEvents();

  function dateKey(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
  }
  function showEventInfo(dayActivities) {
    calendarInfo.replaceChildren();
    if (!dayActivities.length) {
      calendarInfo.textContent = "No activities scheduled for this date.";
      return;
    }
    dayActivities.forEach((activity, index) => {
      const item = document.createElement("div");
      const title = document.createElement("strong");
      title.textContent = `${activity.title} (${activity.type})`;
      const details = document.createElement("div");
      details.textContent = `${activity.time} · ${activity.location}`;
      item.appendChild(title);
      item.appendChild(details);
      calendarInfo.appendChild(item);
      if (index < dayActivities.length - 1)
        calendarInfo.appendChild(document.createElement("hr"));
    });
  }
  function renderCalendar() {
    hideCalendarTooltip();
    const year = displayedDate.getFullYear();
    const month = displayedDate.getMonth();
    calendarMonth.textContent = displayedDate.toLocaleString("en-US", {
      month: "long",
      year: "numeric",
    });
    calendarDays.replaceChildren();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    for (let i = 0; i < firstDay; i++)
      calendarDays.appendChild(document.createElement("span"));
    for (let day = 1; day <= daysInMonth; day++) {
      const key = dateKey(year, month, day);
      const dayActivities = activities.filter(
        (activity) => activity.date === key,
      );
      const button = document.createElement("button");
      button.type = "button";
      button.className = "calendar-day";
      button.textContent = day;
      if (dayActivities.length) {
        button.classList.add("has-event", `type-${dayActivities[0].type}`);
        button.setAttribute(
          "aria-label",
          dayActivities
            .map((activity) => `${activity.title} - ${activity.time}`)
            .join("\n"),
        );
        button.addEventListener("mouseenter", () =>
          showCalendarTooltip(button, dayActivities),
        );
        button.addEventListener("mouseleave", hideCalendarTooltip);
        button.addEventListener("focus", () =>
          showCalendarTooltip(button, dayActivities),
        );
        button.addEventListener("blur", hideCalendarTooltip);
        button.addEventListener("click", () => showEventInfo(dayActivities));
      } else {
        button.addEventListener("click", () => {
          hideCalendarTooltip();
          showEventInfo([]);
        });
      }
      calendarDays.appendChild(button);
    }
    calendarInfo.textContent =
      "Hover or select a highlighted date to view activities.";
  }
  if (calendarPrev)
    calendarPrev.addEventListener("click", () => {
      displayedDate.setMonth(displayedDate.getMonth() - 1);
      renderCalendar();
    });
  if (calendarNext)
    calendarNext.addEventListener("click", () => {
      displayedDate.setMonth(displayedDate.getMonth() + 1);
      renderCalendar();
    });
  window.addEventListener("scroll", hideCalendarTooltip, true);
  window.addEventListener("resize", hideCalendarTooltip);
  renderCalendar();
});

// =========================
// ANNOUNCEMENT SLIDER
// =========================

document.addEventListener("DOMContentLoaded", function () {
  const slider = document.getElementById("announcementSlider");

  if (!slider) return;

  const slides = slider.querySelectorAll(".announcement-slide");
  const dots = document.querySelectorAll(".announcement-dot");
  const counter = document.getElementById("announcementCounter");
  const prevBtn = document.getElementById("announcementPrev");
  const nextBtn = document.getElementById("announcementNext");

  if (slides.length === 0) return;

  let currentIndex = 0;
  let timer = null;

  function showSlide(index) {
    currentIndex = (index + slides.length) % slides.length;

    slides.forEach((slide, i) => {
      slide.classList.toggle("active", i === currentIndex);
    });

    dots.forEach((dot, i) => {
      dot.classList.toggle("active", i === currentIndex);
    });

    if (counter) {
      counter.textContent =
        String(currentIndex + 1).padStart(2, "0") +
        " / " +
        String(slides.length).padStart(2, "0");
    }
  }

  function stopTimer() {
    if (timer !== null) {
      clearInterval(timer);
      timer = null;
    }
  }

  function startTimer() {
    stopTimer();

    if (slides.length > 1 && !document.hidden) {
      timer = setInterval(() => {
        showSlide(currentIndex + 1);
      }, 5000);
    }
  }

  prevBtn?.addEventListener("click", () => {
    showSlide(currentIndex - 1);
    startTimer();
  });

  nextBtn?.addEventListener("click", () => {
    showSlide(currentIndex + 1);
    startTimer();
  });

  dots.forEach((dot, index) => {
    dot.addEventListener("click", () => {
      showSlide(index);
      startTimer();
    });
  });

  slider.addEventListener("mouseenter", stopTimer);
  slider.addEventListener("mouseleave", startTimer);

  document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
      stopTimer();
    } else {
      startTimer();
    }
  });

  // Hide navigation buttons when only one announcement exists
  if (slides.length === 1) {
    if (prevBtn) prevBtn.style.display = "none";
    if (nextBtn) nextBtn.style.display = "none";
  }

  showSlide(0);
  startTimer();
});
