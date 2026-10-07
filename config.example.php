<?php
// Edit this file to adapt the tool to ANY two establishments.
return [
  'app_name'  => 'Dual Analytics',
  'currency'  => 'XAF',
  'timezone'  => 'Africa/Douala',
  'tax_pct'   => 0,
  'at_risk_days' => 14,
  'db' => ['host'=>'127.0.0.1','port'=>3307,'name'=>'dual_analytics','user'=>'root','pass'=>''],  // Exactly two establishments. "activities" = what staff pick at check-in; "items" = POS catalogue (name => price).
  'venues' => [
    ['name'=>'Restaurant','activities'=>['Dine-in','Takeaway'],
     'items'=>['Grilled Chicken Salad'=>4500,'Steak & Fries'=>6000,'Pasta Bolognese'=>3500,'Protein Smoothie'=>2000]],
    ['name'=>'Fitness Centre','activities'=>['Cardio','Weightlifting','Group Class','Yoga','Swimming'],
     'items'=>['Day Pass'=>1500,'Monthly Membership'=>20000,'Class Pack (5)'=>7500]],
  ],
];
