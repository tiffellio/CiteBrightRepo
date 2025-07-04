<?php
// enable debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

if ($_SERVER["REQUEST_METHOD"] == "POST") { //if user submits form

    $prompt = $_POST["prompt"]; //store user prompt

    // ************** Google Search API ***********************
    // Currently set to all Google Search Results and grabs the top 2
    // Can instead use specific academic or specialised search engines
    $apiKey = 'AIzaSyBT6J6tJf5uC89uavmsJYB0bkKGRtc1IAY';
    $cx = 'f446fcbe8426e4ce6';
    $query = urlencode($prompt);
    $searchUrl = "https://www.googleapis.com/customsearch/v1?key=$apiKey&cx=$cx&q=$query";


    // call Google API and turn result into a php array
    $googleCurl = curl_init($searchUrl);
    curl_setopt($googleCurl, CURLOPT_RETURNTRANSFER, true);
    $searchResult = curl_exec($googleCurl);
    curl_close($googleCurl);
    $searchData = json_decode($searchResult, true);     // turn raw json text returned from google search api into an php array
    $context = "";

    // take the first 5 results, download the webpage, 
    // DomXPath extracts the body from an html page,
    // then the cleaned text is appended
    $sourceList = [];
    $counter = 1;
    $maxSources = 5;

    foreach (array_slice($searchData['items'], 0, $maxSources) as $item) {

        $title = $item['title'];
        $link = $item['link'];

        $html = @file_get_contents($link); //load webpage html

        //check if the content failed to fetch and display message
        //save the source link so we can still add this to the retreived sources list later
        if ($html === false) {
            $context .= "[Source $counter] $title: [Could not fetch content] ($link)\n\n";
            $sourceList[] = "[Source $counter] $link"; //store link separetely for front end
            $counter++;
            continue;
        }

        libxml_use_internal_errors(true); // hide warning about malformed html
        $doc = new DOMDocument(); // needed to work with HTML in php
        $doc->loadHTML($html); //load into memory for processing

        // only extract the <p> tags to strip out irrelevant info
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//p');
        $text = '';

        // loop through each <p> node and cat to text content
        foreach ($nodes as $node) {
            $text .= $node->nodeValue . "\n";
        }

        $mainText = substr(strip_tags($text), 0, 2000); // strip to 2000 characters so the response isn't too long
        $context .= "[Source $counter] $title: $mainText ($link)\n\n";
        $sourceList[] = "[Source $counter] $link";
        $counter++;
    }

    // build source map for highlights
    $sourceMap = [];
    $sourceIndex = 1;
    $context = "";

    foreach (array_slice($searchData['items'], 0, $maxSources) as $item) {
        $title = $item['title'];
        $link = $item['link'];

        $html = @file_get_contents($link);
        $text = '';

        if ($html !== false) {
            libxml_use_internal_errors(true);
            $doc = new DOMDocument();
            $doc->loadHTML($html);
            $xpath = new DOMXPath($doc);
            $nodes = $xpath->query('//p');

            foreach ($nodes as $node) {
                $text .= $node->nodeValue . "\n";
            }

            $mainText = substr(strip_tags($text), 0, 2000);
        } else {
            $mainText = "[Could not fetch content]";
        }

        // Append to context
        $context .= "[Source $sourceIndex] $title: $mainText ($link)\n\n";

        // Build source map
        $sourceMap["Source $sourceIndex"] = [
            "url" => $link,
            "title" => $title,
            "text" => substr($mainText, 0, 300)
        ];

        $sourceIndex++;
    }

    // *********************************************************




    //json payload
    // tell deepseek to only answer using the sources retreived from above in 
    // specific format to enable src highlighting later
    $data = [
        "model" => "deepseek-llm-7b-chat",
        "messages" => [
            // Instruction
            ["role" => "system", "content" =>
                "You are an assistant who must only answer questions using the provided sources. " .
                "If the answer is not in the sources, say 'I don't know.' You must include the exact source reference in the format " .
                "[Source 1], [Source 2], etc., immediately after any factual statement drawn from the sources. Do not combine multiple sources like [Sources 1 and 2], instead, repeat them separately as [Source 1] [Source 2]." .
                "If you fail to do this, your response will be discarded."
            ],

            // injected dynamic context from Google
            ["role" => "user", "content" => "Sources:\n\n" . $context],

            // Few-shot example to teach citation formatting
            ["role" => "user", "content" => "What's the capital of France?"],
            ["role" => "assistant", "content" => "The capital of France is Paris. [Source 1]"],

            // Example 2: multi-source citation
            ["role" => "user", "content" => "Tell me some facts about the moon."],
            ["role" => "assistant", "content" =>
                "The moon orbits the Earth approximately every 27.3 days. [Source 2] " .
                "It causes ocean tides on Earth due to its gravitational pull. [Source 3] " .
                "The surface of the moon is covered in regolith, a layer of dust and rock. [Source 1] [Source 4]"
            ],

            // Example 3: no known answer
            ["role" => "user", "content" => "What is the square root of invisible ink?"],
            ["role" => "assistant", "content" => "I don't know."],

            // Actual user prompt
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.7 // this appears to be the best value for instruction following
    ];

    //use curl to POST to LM studio:
    //the path /v1/chat/completions is what OpenAI uses for LM studio
    $ch = curl_init('http://127.0.0.1:1234/v1/chat/completions'); //assign api response as a var
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a string dont output in browser
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); //turn php array into json string
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']); //send in json format

    $result = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($result, true);
    $full = $response['choices'][0]['message']['content'];

    // replace source x with tooltip highlights
    // acheived by using span tags with title attr.
    foreach ($sourceMap as $sourceTag => $data) {
        $tooltip = htmlspecialchars($data['url'], ENT_QUOTES);
        $highlight = "<span class='highlight' title='$tooltip'>[$sourceTag]</span>";
        $full = str_replace("[$sourceTag]", $highlight, $full);
    }


    //simulates the source highlighting by splitting text in half:
    // NOTE: NO LONGER NEEDED TEST REMOVAL LATER
    $sentences = preg_split('/(?<=[.?!])\s+/', $full); //split into sentences

    //make sure there are atleast >1 sentence
    if (count($sentences) >= 2) {

        $first = implode(' ', array_slice($sentences, 0, 1)); //first sentence
        $second = implode(' ', array_slice($sentences, 1)); //the rest
    } else {
        // fallback if only one sentence
        $first = $full;
        $second = '';
    }

    $sourcesEncoded = urlencode(json_encode($sourceMap)); // send the proper numbered source map
    $encodedAnswer = urlencode($full); // send the entire LLM answer directly
    header("Location: index.php?answer=$encodedAnswer&sources=$sourcesEncoded");
    exit;

    /*echo "<pre>";
    echo "DEBUG INFO \n\n";
    echo "Prompt Sent to LLM:\n\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    echo "Context:\n\n" . $context . "\n\n";
    echo "LLM Output:\n\n" . $full . "\n\n";
    echo "Sources Used:\n\n" . print_r($sourceMap, true);
    echo "\n\n";
    echo "</pre>";
    exit;*/


} 
?>
