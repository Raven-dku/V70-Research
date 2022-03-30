<?php
/**
 *  Vida Database converter. 
 *  Vida should be pre-installed in PC.
 *  Created by Tomas Rad (Raven).
 */

/** CONFIGURATION GOES HERE */
$LanguageID = 15;
$SQL_ServerName = "RAVEN\\VIDA";
$SQL_DatabaseName = "DiagSwdlRepository";
$Count = 0;

$Debug = TRUE;

$ModelsAllowed = array(
    'mdl285yr2001'
);

/** DO NOT EDIT BELOW THIS LINE!! */
$connectionInfo = array( "Database" => "$SQL_DatabaseName");
$conn = sqlsrv_connect( $SQL_ServerName, $connectionInfo);

echo "[DATABASE] Connecting to $SQL_ServerName...".PHP_EOL;

if( $conn ) {
    echo "[DATABASE] Connection established!".PHP_EOL;

    $sql = "SELECT TOP (100000) [fkScript],[DisplayText],[XmlDataCompressed] FROM [DiagSwdlRepository].[dbo].[ScriptContent]";
    $params = array();
    $options =  array( "Scrollable" => SQLSRV_CURSOR_KEYSET );
    $stmt = sqlsrv_query( $conn, $sql , $params, $options );
    $row_count = sqlsrv_num_rows( $stmt );
    if ($row_count === false) {
        echo "[ERROR] No queries have been found! Is your VIDA database empty?".PHP_EOL;
        exit();
    }

    echo "[DATABASE] Found $row_count records.".PHP_EOL;

    if (!file_exists('temp')) {
        mkdir('temp', 0777, true);
    }

    while( $row = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC) ) {
        $LineName = $row['DisplayText'];
        $ScriptName = $row['fkScript'];

        echo "Processing \"$LineName\" ...";

        // Sometimes text might have backslash, and php WILL interpret it as a subdirectory. That's not good...
        $LineName = str_replace('/', ' or ', $LineName);

        // Initialize library
        $zip = new ZipArchive;

        // Save to folder as ZIP file
        file_put_contents("temp/$ScriptName-zip.temp", $row['XmlDataCompressed']);
        // Unzip file and rename file inside to XML
        $Result = $zip->open("temp/$ScriptName-zip.temp");

        if ($Result == TRUE) {
            for( $i = 0; $i < $zip->numFiles; $i++ )
                $stat = $zip->statIndex( $i );

            //$zip->extractTo("temp/$ScriptName-xml.temp");
            copy("zip://temp/$ScriptName-zip.temp"."#".$ScriptName, "temp/$ScriptName-xml.temp");
            $zip->close();

            // Load XML File into memory...
            $xml = file_get_contents("temp/$ScriptName-xml.temp");

            // Replace car profile string because SimpleXML can't read colon properties.
            // Maybe it can, but eh this is faster.
            $xml = str_replace('nevis:expl-profile=', 'cars=', $xml);

            // Actually load XML...
            $xml = simplexml_load_string($xml);

            $result = xml2array($xml->attributes()->cars);

            $Valid = false;

            $CarArray = explode(",", trim($result[0]));

            foreach($CarArray as $Car)
                foreach($ModelsAllowed as $Model)
                    if(trim($Car) === $Model) {
                        $Count++;
                        $Valid = true;
                    }

            // It seems that when there's no lines in nevis:expl-profile, it fits all cars?
            if(empty($CarArray))
                $Valid = true;

            if($Valid)
            {
                // Fetched XML is compressed and ugly, we need to fix this before putting into final position.
                $xml->formatOutput = true;
                $xmlOutput = $xml->saveXML();

                // Revert SimpleXML hack.
                $xmlOutput = str_replace('cars=', 'nevis:expl-profile=', $xmlOutput);

                // Save file!
                file_put_contents("xml/$LineName.xml", $xmlOutput);

                echo " | SUCCESS".PHP_EOL;
            }
            else
                echo " | SKIPPING".PHP_EOL;

            // Unlink temp files...
            unlink("temp/$ScriptName-zip.temp");
            unlink("temp/$ScriptName-xml.temp");


        }
    }
}else{
    echo "Connection could not be established. Please check your configuration, and try again.".PHP_EOL;
    die( print_r( sqlsrv_errors(), true));
}


function xml2array ( $xmlObject, $out = array () )
{
    foreach ( (array) $xmlObject as $index => $node )
        $out[$index] = ( is_object ( $node ) ) ? xml2array ( $node ) : $node;

    return $out;
}
?>