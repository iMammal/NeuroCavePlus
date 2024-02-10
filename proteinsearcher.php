<?php
$db = new SQLite3('humap2.db');

//get gene name from the url
$searchTerms = $_GET['search'];
//sanitize the search term
$searchGene = filter_var($searchTerms, FILTER_SANITIZE_STRING);

//if search term is empty, set it to empty string
if ($searchTerms == "" || $searchTerms == null || $searchTerms == "undefined") {
  $error = array('error' => 'No search term');
  echo json_encode($error);
  $db->close();
  exit();
  $searchGene = "GAK";
}

// drop tables tempTopology, tempNetwork, tempMetadata if they exist
$db->exec('DROP TABLE IF EXISTS tempTopology');
$db->exec('DROP TABLE IF EXISTS tempNetwork');
$db->exec('DROP TABLE IF EXISTS tempMetadata');
//$db->exec('DROP TABLE IF EXISTS tempComplexes');
//$db->exec('DROP TABLE IF EXISTS tempGenes');

// create table tempTopology with columns label, complexId with unique constraint on label that automatically increments
$db->exec('CREATE TABLE tempTopology (label TEXT, complexIdClustering TEXT)');

// create table tempNetwork with columns source, target, interaction
$db->exec('CREATE TABLE tempNetwork (source TEXT, target TEXT, interaction TEXT)');
// create table tempMetadata with columns label,complexId, geneName, confidence
//$db->exec('CREATE TABLE tempMetadata (label TEXT, complexid TEXT, region_name TEXT, confidence TEXT, hemisphere TEXT');
$db->exec('CREATE TABLE tempMetadata (label TEXT, complexid TEXT, region_name TEXT, confidence TEXT, hemisphere TEXT)');

// create table tempComplexes with columns complexNumber, complexId, confidence
//$db->exec('CREATE TABLE tempComplexes (complexNumber INTEGER, complexId TEXT, confidence TEXT)');
//// create table tempGenes with columns geneNumber, geneName
//$db->exec('CREATE TABLE tempGenes (geneNumber INTEGER, geneName TEXT)');



//execute the query to get the HuMAP2_ID, Confidence, Uniprot_ACCs, and genenames from the database
$query = 'SELECT HuMAP2_ID, Confidence, Uniprot_ACCs, genenames FROM HuMAP2_ID where genenames like "%'.$searchGene.'%" ';
//dump php variable to console
//var_dump($query);
$results = $db->query($query);

echo json_encode($results);
$genecounter = 1;
$complexcounter = 1;

$jsonReturn = array();

//iterate through each row of the result
while ($row = $results->fetchArray()) {
  //echo the HuMAP2_ID, Confidence, Uniprot_ACCs, and genenames
  echo "HuMAP2_ID: " . $row['HuMAP2_ID'] . " Confidence: " . $row['Confidence'] . " Uniprot_ACCs: " . $row['Uniprot_ACCs'] . " genenames: " . $row['genenames'] . "<br>";

  $complexId = $row['HuMAP2_ID'];

  // split the genenames by space
  $genenames = explode(" ", $row['genenames']);

  //iterate through each genename
  foreach ($genenames as $genename) {
    //echo the genename with counter
    echo "Genename".$genecounter.": " . $genename . "<br>";

    //insert into tempTopology table the HuMAP2_ID and genenames with label counting upp from 1
    $stmt = $db->prepare('INSERT INTO tempTopology (label, complexIdClustering) VALUES (:label, :complexIdClustering )');
    $stmt->bindValue(':label', $genecounter, SQLITE3_TEXT);
    $stmt->bindValue(':complexIdClustering', $complexcounter, SQLITE3_TEXT); // $row['HuMAP2_ID']
    $result = $stmt->execute();
    if (!$result) {
      echo "Error inserting data: " . $db->lastErrorMsg();
    }

    //insert into tempMetadata table the label, clusterid, region_name, and confidence
    $query = 'INSERT INTO tempMetadata (label, complexid, region_name, confidence, hemisphere) VALUES (:label, :complexid, :region_name, :confidence, :hemisphere)';
    $stmt = $db->prepare($query);
    $stmt->bindValue(':label', $genecounter, SQLITE3_TEXT);
    $stmt->bindValue(':complexid', $complexId, SQLITE3_TEXT);
    $stmt->bindValue(':region_name', $genename, SQLITE3_TEXT);
    $stmt->bindValue(':confidence', $row['Confidence'], SQLITE3_TEXT);
    $stmt->bindValue(':hemisphere', "left", SQLITE3_TEXT);
    $result = $stmt->execute();
    if (!$result) {
      echo "Error inserting data: " . $db->lastErrorMsg();
    }

    //increment the counters
    $genecounter++;

  }

  //insert into tempTopology table the HuMAP2_ID and genenames with label counting upp from 1

  $complexcounter++;
}

// for each number from 1 to genecounter, insert into tempNetowrk table the source, target, and interaction
for ($i = 1; $i < $genecounter; $i++) {
  //for ($j = $i; $j < $genecounter; $j++) {  // upper triangle
  for ($j = 1; $j < $genecounter; $j++) {  // full matrix
    if ($i != $j) {
      //query the tempMetadate table to get the geneName for the source
      $source = $db->querySingle('SELECT region_name FROM tempMetadata WHERE label = "'.$i.'"');
      //query the tempMetadate table to get the geneName for the target
      $target = $db->querySingle('SELECT region_name FROM tempMetadata WHERE label = "'.$j.'"');

      //query the pin table to get the interaction for the source and target
      $interaction = $db->querySingle('SELECT interaction FROM pin WHERE proteinA = "'.$source.'" AND proteinB = "'.$target.'"');
      if ($interaction == "") {
        $interaction = $db->querySingle('SELECT interaction FROM pin WHERE proteinA = "'.$target.'" AND proteinB = "'.$source.'"');
      }

      if ($interaction != "") {

        $stmt = $db->prepare('INSERT INTO tempNetwork (source, target, interaction) VALUES (:source, :target, :interaction)');
        $stmt->bindValue(':source', $i, SQLITE3_TEXT);
        $stmt->bindValue(':target', $j, SQLITE3_TEXT);
        $stmt->bindValue(':interaction', $interaction, SQLITE3_TEXT);

        $result = $stmt->execute();
        if (!$result) {
          echo "Error inserting data: " . $db->lastErrorMsg();
        }
      }
    }
  }
}

$db->close();


//   // Trash code from proteinsearcher.php
//execute the query to get the proteinA and proteinB from the database
//$results = $db->query('SELECT proteinA, proteinB FROM pin');

//iterate through each row of the result
//while ($row = $results->fetchArray()) {
//echo the proteinA and proteinB
//echo "ProteinA: " . $row['proteinA'] . " ProteinB: " . $row['proteinB'] . "<br>";
//}
//   //execute query to select complexNumber from tempComplexes where complexId = $row['HuMAP2_ID']
//    $complexNumber = $db->querySingle('SELECT complexNumber FROM tempComplexes WHERE complexId = "'.$row['HuMAP2_ID'].'"');
//    $stmt->bindValue(':complexId', $complexNumber, SQLITE3_TEXT);
//    $result = $stmt->execute();
//    if (!$result) {
//      echo "Error inserting data: " . $db->lastErrorMsg();
//    }
//
//    //insert into tempGenes table the geneNumber and genename
//    $stmt = $db->prepare('INSERT INTO tempGenes (geneNumber, geneName) VALUES (:geneNumber, :geneName)');
//    $stmt->bindValue(':geneNumber', $genecounter, SQLITE3_TEXT);
//    $stmt->bindValue(':geneName', $genename, SQLITE3_TEXT);
//    $result = $stmt->execute();
//    if (!$result) {
//      echo "Error inserting data: " . $db->lastErrorMsg();
//    }
//  //insert into tempComplexes table the complexNumber, HuMAP2_ID, and Confidence
//  $stmt = $db->prepare('INSERT INTO tempComplexes (complexNumber, complexId, confidence) VALUES (:complexNumber, :complexId, :confidence)');
//  $stmt->bindValue(':complexNumber', $complexcounter, SQLITE3_TEXT);
//  $stmt->bindValue(':complexId', $row['HuMAP2_ID'], SQLITE3_TEXT);
//  $stmt->bindValue(':confidence', $row['Confidence'], SQLITE3_TEXT);
//  $result = $stmt->execute();
//
//  if (!$result) {
//    echo "Error inserting data: " . $db->lastErrorMsg();
//  }

echo "Done!";
?>