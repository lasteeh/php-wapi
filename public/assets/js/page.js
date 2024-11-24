document.addEventListener("DOMContentLoaded", () => {
  const page = {
    init: () => {
      // frontend js goes here
      console.log("page.js loaded");
    },
  };

  window.addEventListener("load", () => {
    page.init();
  });
});
