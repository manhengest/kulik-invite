(function () {
  "use strict";

  var TRACK_KEYS = [
    "utm_source",
    "utm_medium",
    "utm_campaign",
    "utm_content",
    "utm_term",
    "fbclid",
  ];

  function pushEvent(name) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event: name });
  }

  function readParams() {
    var params = new URLSearchParams(window.location.search);
    var out = {};
    TRACK_KEYS.forEach(function (key) {
      var value = params.get(key);
      if (value) out[key] = value;
    });
    return out;
  }

  function token() {
    if (window.crypto && crypto.randomUUID) {
      return crypto.randomUUID();
    }
    return (
      "t-" +
      Date.now().toString(36) +
      "-" +
      Math.random().toString(36).slice(2, 12)
    );
  }

  function fillHidden() {
    var tracked = readParams();
    TRACK_KEYS.forEach(function (key) {
      var el = document.getElementById(key);
      if (el) el.value = tracked[key] || "";
    });
    var pageUrl = document.getElementById("page_url");
    if (pageUrl) pageUrl.value = window.location.href;
    var started = document.getElementById("form_started_at");
    if (started) started.value = String(Date.now());
    var formToken = document.getElementById("form_token");
    if (formToken) formToken.value = token();
  }

  function smoothScroll(hash) {
    var target = document.querySelector(hash);
    if (!target) return;
    target.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  document.querySelectorAll(".js-scroll-invite").forEach(function (link) {
    link.addEventListener("click", function (event) {
      event.preventDefault();
      smoothScroll("#invitation");
      history.replaceState(null, "", "#invitation");
    });
  });

  var form = document.getElementById("invite-form");
  if (!form) return;

  fillHidden();

  var formStartSent = false;
  form.addEventListener(
    "focusin",
    function () {
      if (formStartSent) return;
      formStartSent = true;
      pushEvent("form_start");
    },
    true
  );

  var statusEl = document.getElementById("form-status");
  var submitBtn = document.getElementById("submit-btn");

  function showStatus(state, message) {
    if (!statusEl) return;
    statusEl.hidden = false;
    statusEl.dataset.state = state;
    statusEl.textContent = message;
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;

    if (submitBtn) submitBtn.disabled = true;
    showStatus("ok", "Sending…");

    var body = new FormData(form);

    fetch(form.action, {
      method: "POST",
      body: body,
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      })
      .then(function (result) {
        if (result.ok && result.data && result.data.ok) {
          pushEvent("form_submit");
          showStatus(
            "ok",
            "Thank You! Your Invitation Request Has Been Received. We'll be in touch with the event details."
          );
          form.reset();
          fillHidden();
          return;
        }
        var message =
          (result.data && result.data.error) ||
          "Something went wrong. Please try again.";
        showStatus("error", message);
      })
      .catch(function () {
        showStatus(
          "error",
          "Something went wrong. Please try again."
        );
      })
      .finally(function () {
        if (submitBtn) submitBtn.disabled = false;
      });
  });
})();
