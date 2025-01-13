<!--fonction pour remonter et descendre d'une page en un saut-->
    <script>
      "use strict";
      //(c) Pascal Myrta
      function toTop() {
        var yCoord = window.pageYOffset;
        window.scrollBy(0, -100);
        var trigger = setTimeout(toTop, 1);
        if (yCoord === 0) {clearTimeout(trigger); };
      }
      function toBottom() {
        var fullPage = document.body.offsetHeight;
        var window_height = window.innerHeight;
        var yCoord = window.pageYOffset;
        window.scrollBy(0, 100);
        var trigger = setTimeout(toBottom, 1);
        if (fullPage - yCoord <= window_height) {clearTimeout(trigger); };
      }
    </script>