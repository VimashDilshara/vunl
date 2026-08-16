<?php

// --- Database Credentials ---
$host = "localhost";
$dbname = "u790354595_Ywzyy"; 
$user = "u790354595_BaEUo";       
$pass = "Ej2wT3x9kR";           

// --- HTML Styles ---
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advanced PHP DB Shell</title>
    <style>
        body { font-family: "Segoe UI", monospace; background-color: #1e1e1e; color: #d4d4d4; margin: 20px; }
        h1, h2 { color: #569cd6; border-bottom: 1px solid #3e3e3e; padding-bottom: 10px;}
        form { background: #252526; padding: 20px; border-radius: 5px; border: 1px solid #3e3e3e; }
        textarea { width: 100%; height: 200px; background-color: #1e1e1e; color: #9cdcfe; border: 1px solid #3e3e3e; font-family: monospace; font-size: 13px; padding: 10px; box-sizing: border-box; }
        input[type="submit"] { background-color: #007acc; color: white; padding: 10px 20px; border: none; cursor: pointer; font-size: 14px; margin-top: 10px; border-radius: 2px; }
        input[type="submit"]:hover { background-color: #005f9e; }
        input[type="file"] { margin-top: 10px; color: #d4d4d4; }
        .log-container { background: #000; padding: 15px; border: 1px solid #333; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; margin-top: 20px; }
        .log-success { color: #4ec9b0; margin: 2px 0; }
        .log-error { color: #f44747; margin: 2px 0; border-left: 3px solid #f44747; padding-left: 5px; }
        .log-info { color: #569cd6; font-weight: bold; margin-top: 10px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 13px; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        th { background-color: #2d2d30; color: #569cd6; }
        tr:nth-child(even) { background-color: #252526; }
    </style>
</head>
<body>';

echo '<h1>PHP Database Manager</h1>';

try {
    // Connect to DB
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0); // Disable emulation for cleaner error reporting in loop
    
    echo '<p style="color:#4ec9b0">✅ Connected to: ' . htmlspecialchars($dbname) . '</p>';

    $sql_input = '';
    
    // Handle File Upload or Text Input
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] == UPLOAD_ERR_OK) {
        $sql_input = file_get_contents($_FILES['sql_file']['tmp_name']);
        echo '<p style="color:#ce9178">📂 Loaded file: ' . htmlspecialchars($_FILES['sql_file']['name']) . '</p>';
    } elseif (isset($_POST['query']) && !empty($_POST['query'])) {
        $sql_input = $_POST['query'];
    }

    echo '<form method="post" enctype="multipart/form-data">
        <label>SQL Query / Content:</label><br>
        <textarea name="query" placeholder="Paste SQL here OR upload a .sql file below...">' . htmlspecialchars($sql_input) . '</textarea><br>
        <div style="margin-top:10px;">
            <label>Upload .sql File:</label> 
            <input type="file" name="sql_file" accept=".sql">
        </div>
        <input type="submit" value="Execute Query">
    </form>';

    if (!empty($sql_input)) {
        echo '<h2>Execution Log:</h2><div class="log-container">';
        
        // --- 1. Check if it's a SELECT query (Single query mode) ---
        $trimmed_sql = trim($sql_input);
        if (stripos($trimmed_sql, 'SELECT') === 0 && stripos($trimmed_sql, ';') === false) {
            // Run as single SELECT
            try {
                $stmt = $conn->query($trimmed_sql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo '<div class="log-info">Fetched ' . count($results) . ' rows.</div></div>'; // Close log
                
                if (count($results) > 0) {
                    echo '<table><thead><tr>';
                    foreach (array_keys($results[0]) as $col) echo "<th>$col</th>";
                    echo '</tr></thead><tbody>';
                    foreach ($results as $row) {
                        echo '<tr>';
                        foreach ($row as $val) echo "<td>" . htmlspecialchars($val) . "</td>";
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            } catch (PDOException $e) {
                echo '<div class="log-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
            }
        } 
        // --- 2. Multi-Query Execution Mode (For Dumps) ---
        else {
            // Function to split SQL by semicolon but not inside quotes (Basic implementation)
            // For large dumps, standard splitting by ";\n" is safer and faster
            
            // Normalize line endings
            $sql_input = str_replace("\r\n", "\n", $sql_input);
            $queries = explode(";\n", $sql_input); // Split by semicolon at end of line
            
            $success_count = 0;
            $fail_count = 0;

            foreach ($queries as $query) {
                $query = trim($query);
                if (empty($query)) continue;

                // Restore the semicolon for the execution if needed, usually exec doesn't strictly need it but good for log
                // PDO exec doesn't need ; at end
                
                try {
                    $conn->exec($query);
                    
                    // Create a short preview for log
                    $preview = substr(str_replace("\n", " ", $query), 0, 80);
                    if (strlen($query) > 80) $preview .= "...";
                    echo '<div class="log-success">✔ Executed: ' . htmlspecialchars($preview) . '</div>';
                    $success_count++;
                } catch (PDOException $e) {
                    $fail_count++;
                    echo '<div class="log-error">❌ FAILED: ' . htmlspecialchars($e->getMessage()) . '<br><small>Query: ' . htmlspecialchars(substr($query, 0, 100)) . '...</small></div>';
                }
            }
            
            echo '<div class="log-info">Process Complete. Success: ' . $success_count . ' | Failed: ' . $fail_count . '</div>';
            echo '</div>'; // Close log container
        }
    }

} catch(PDOException $e) {
    echo '<div class="error" style="color:red; background: #330000; padding:10px;"><strong>Database Connection Failed:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
}

echo '</body></html>';
?>
   
