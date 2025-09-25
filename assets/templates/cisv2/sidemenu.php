<?php 

$mainCategories = getNavigationMenus();
$userID = isset($_SESSION["userID"]) ? (int)$_SESSION["userID"] : 0;
$permissionItems = $userID > 0 ? getCurrentUserPermissions($userID) : [];
$organisedCats = array();
foreach($mainCategories as $c){
  $c->itemsArray = array();
  foreach($permissionItems as $pi){
    if ($c->id == $pi->navigation_id && $pi->show_in_sidemenu == 1){        
      array_push($c->itemsArray,$pi);
    }
  }
  array_push($organisedCats,$c);
}

?>

<div class="sidebar">
  <nav class="sidebar-nav">
    <ul class="nav">
    <li class="nav-item open">
        <a class="nav-link active" href="/index.php">View Dashboard</a>
    </li>
    <?php 

      foreach($organisedCats as $c){
        
        if (count($c->itemsArray) > 0){
          echo '<li class="nav-title">'.$c->title.'</li>';

          foreach($c->itemsArray as $i){

          echo '<li class="nav-item">
                    <a class="nav-link" href="'.$i->filename.'">'.$i->name.'</a>
                </li>';
          }
        }
      }
    ?>
    </ul>
  </nav>
</div>