<?php
// Create or open a database file
$db = new PDO('sqlite:myDatabase.sqlite3');


// Wrap your code in a try statement and catch PDOException
try {    

// Creating a table
$db->exec(
"CREATE TABLE IF NOT EXISTS myTable (
    id INTEGER PRIMARY KEY, 
    title TEXT, 
    value TEXT)"
);

// Inserting multiple records at once
$items = array(
    array(
        'title' => 'Hello!',
        'value' => 'Just testing...',
    ),
    array(
        'title' => 'Hello Twice!',
        'value' => 'Who is there?',
    ),
);

// Prepare INSERT statement to SQLite3 file db
$insert = "INSERT INTO myTable (title, value) VALUES (:title, :value)";
$statement = $db->prepare($insert);

// Bind parameters to statement variables
$statement->bindParam(':title', $title);
$statement->bindParam(':value', $value);

// Insert all of the items in the array
foreach ($items as $item) {
    $title = $item['title'];
    $value = $item['value'];

    $statement->execute();
}



// Querying
$result = $db->query('SELECT * FROM myTable')->fetchAll();
var_dump($result);
foreach ($result as $result) {
    print $result['id'];
}

} catch(PDOException $e) {
    echo $e->getMessage();
}
?>
