<?php

include("assets/functions/config.php"); 



//######### AJAX ENDS HERE #########

   // ######### CSS BEGINS HERE ######### -->

  // ######### CSS BEGINS HERE ######### -->


//######### HEADER BEGINS HERE ######### -->

include("assets/template/html-header.php");
include("assets/template/header.php");

//######### HEADER ENDS HERE ######### -->

?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">

  <div class="app-body">
    <?php include("assets/template/sidemenu.php"); ?>
    <main class="main">
      <!-- Breadcrumb -->
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">
          <a href="#"><!--    #### PAGE PARENT GOES HERE #### --></a>
        </li>
        <li class="breadcrumb-item active"><!--    #### PAGE NAME GOES HERE #### --></li>
        <!-- Breadcrumb Menu-->
        <li class="breadcrumb-menu d-md-down-none">
          <?php include('assets/template/quick-product-search.php'); ?>
        </li>
      </ol>
      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="row">
            <div class="col ">
              <div class="card">
                <div class="card-header">
                  <h4 class="card-title mb-0"><!--    #### PAGE CONTENT GOES HERE #### --></h4>
                  <div class="small text-muted"><!--    #### PAGE BLURB OR DESCRIPTION GOES HERE #### --></div>
                </div>
                <div class="card-body">
                  <div class="cis-content">
                  
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!--/.row-->
        </div>
      </div>
    </main>
    <!-- ######### FOOTER BEGINS HERE ######### -->
    <?php include("assets/template/personalisation-menu.php"); ?>
  </div>

  <!-- ######### JAVASSCRIPT BEGINS HERE ######### -->

  <!-- ######### JAVASSCRIPT ENDS HERE ######### -->

  <?php include("assets/template/html-footer.php"); ?>
  <?php include("assets/template/footer.php"); ?>
  <!-- ######### FOOTER ENDS HERE ######### -->