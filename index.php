<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>CiteBright</title>

    <style>
      body {
        font-family: Arial; 
        max-width: 600px; 
        margin: 50px auto;
      }

      textarea, input[type="submit"] {
        width: 100%; 
        padding: 10px; 
        font-size: 16px; 
        margin-top: 10px;
      }

      .highlight {
          background-color: yellow;
          cursor: help;
          border-radius: 4px;
          padding: 0 3px;
      }

    .gap1 {
      background-color: #d1eaff;
      display: inline-block;
      width: 10px;
      height: 1em;
    }

    .gap2 {
      background-color: #ffe4e1;
      display: inline-block;
      width: 10px;
      height: 1em;
    }

    </style>
  </head>

  <body>
    <h2>Ask My LLM</h2>
      <?php
        if (isset($_GET['answer']) && isset($_GET['sources'])) {
          $answer = urldecode($_GET['answer']);
          $sources = json_decode(urldecode($_GET['sources']), true);

          foreach ($sources as $label => $data) {
              $answer = str_replace(
                  "[$label]",
                  "<span class='highlight' data-source=\"$label\">[$label]</span>",
                  $answer
              );
          }

          echo "<div class='answer-box'>";
          echo "<h2>LLM Answer</h2>";
          echo "<p>$answer</p>";
          echo "</div>";

          echo "<h3>Sources:</h3><ul>";
          foreach ($sources as $label => $data) {
              $url = htmlspecialchars($data['url'], ENT_QUOTES);
              echo "<li><a href='$url' target='_blank'>$label</a></li>";
          }
          echo "</ul>";
      }


    ?>

    <form method="POST" action="ask.php">
      <textarea name="prompt" rows="4" placeholder="Ask your question."></textarea>
      <input type="submit" value="Send to AI">
    </form>

    <!-- clear button -->
    <form method="get" action="index.php" style="margin-top:10px;">
      <button type="submit">Clear</button>
    </form>

    <div id="popup" style="display:none; position:absolute; background:#fff; border:1px solid #ccc; padding:10px; border-radius:6px; max-width:300px; z-index:9999;"></div>


    <!-- POP UP HTML and JS -->
    <script>
      const sourceData = <?php echo json_encode($sources); ?>;
      const popup = document.getElementById('popup');

      document.addEventListener('click', function(e) {
        if (e.target.classList.contains('highlight')) {
          const key = e.target.getAttribute('data-source');
          const source = sourceData[key];
          if (!source) return;

          popup.innerHTML = `<strong>${source.title}</strong><br>${source.text}<br><a href="${source.url}" target="_blank">Visit Source</a>`;
          popup.style.display = 'block';
          popup.style.top = (e.pageY + 10) + 'px';
          popup.style.left = (e.pageX + 10) + 'px';
        } else {
          popup.style.display = 'none';
        }
      });
    </script>


  </body>
</html>
