document.addEventListener("DOMContentLoaded", () => {
  const app = {
    init: () => {
      // application js goes here
      console.log("application.js loaded");
    },
  };

  window.addEventListener("load", () => {
    app.init();
  });
});
