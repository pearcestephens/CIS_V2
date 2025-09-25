<?php
// CoreUI left sidebar
?>
<div class="sidebar">
  <nav class="sidebar-nav">
    <ul class="nav">
      <li class="nav-item"><a class="nav-link" href="/"><i class="nav-icon fa fa-home"></i> Dashboard</a></li>

      <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon fa fa-exchange-alt"></i> Transfers</a>
        <ul class="nav-dropdown-items">
          <li class="nav-item">
            <a class="nav-link" href="/module/transfers/stock/pack"><i class="nav-icon fa fa-box"></i> Stock Pack</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="/module/transfers/stock/receive"><i class="nav-icon fa fa-inbox"></i> Receive</a>
          </li>
        </ul>
      </li>

      <li class="nav-item nav-dropdown">
        <a class="nav-link nav-dropdown-toggle" href="#"><i class="nav-icon fa fa-file-invoice"></i> Purchase Orders</a>
        <ul class="nav-dropdown-items">
          <li class="nav-item"><a class="nav-link" href="/module/purchase-orders/list"><i class="nav-icon fa fa-list"></i> All Orders</a></li>
        </ul>
      </li>

      <li class="nav-item"><a class="nav-link" href="/settings"><i class="nav-icon fa fa-cog"></i> Settings</a></li>
    </ul>
  </nav>
</div>
