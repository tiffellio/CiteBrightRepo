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

            echo "<div class='answer-box'>";
            echo "<h2>LLM Answer</h2>";
            echo "<p>$answer</p>";
            echo "</div>";

            echo "<h3>Sources:</h3><ul>";
            foreach ($sources as $label => $url) {
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

  </body>
</html>
