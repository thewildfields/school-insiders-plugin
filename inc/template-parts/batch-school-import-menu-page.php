<h1>Batch School Import</h1>

<p>Enter the school url from CollegeDunia.com below to import the school data and create a school post.</p>

<div class="wrap">
    <h2>Upload CSV File</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="text" name="csv_file">
        <input type="submit" value="submit">
    </form>
</div>
<?php

// Handle CSV upload after form submission
if (isset($_FILES['csv_file'])) {
    process_csv_file($_FILES['csv_file']);
}

?>