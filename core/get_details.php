<?php
session_start();
include 'config.php';

$package_id=$_GET['package_id'];

$sql="SELECT * FROM drop_offs WHERE parcel_id = '$package_id'";
$result=$con->query($sql) or die ("error: Server error ".mysqli_error($con));
$countx = mysqli_num_rows($result);

if($result)
{
 
while($rows=mysqli_fetch_array($result))
{

$id = $rows['id'];
$parcel_type = $rows['parcel_type'];
$origin_city = $rows['origin_city'];
$des_city = $rows['des_city'];
$parcel_id = $rows['parcel_id'];
$parcel_size = $rows['parcel_size'];
$parcel_weight = $rows['parcel_weight'];
$origin_address = $rows['origin_terminal_address'];
$des_address = $rows['des_terminal_address'];
$delivery = $rows['delivery'];

$final_des_name = $rows['final_des_name'];
$final_des = $rows['final_des'];

$pickup_date = $rows['pickup_date'];
$pickup_time = $rows['pickup_time'];
$sender = $rows['sender'];
$receiver = $rows['receiver'];

$status = $rows['status'];
$carrier = $rows['carrier'];
$carrier_id = $rows['carrier_id'];





$date_t = $rows['date_t'];

$details = '<div class="card brt border-0 card-style">
                <div class="content mb-0">
                    <div class="list-group list-custom-small">
                       
                        <a href="#">

                            <span>Status</span>
                            <i style="font-size:13px;">'.$status.'</i>
                         </a>
                        <a href="#">

                   
                            <span>Carier</span>
                            <i style="font-size:13px;">'.$carrier.'</i>
                   
                    </a>
                        <a href="#">


                            <span>Parcle Type</span>
                            <i style="font-size:13px;">'.$parcel_type.'</i>
                       
                        </a>
                        <a href="#">

                            <span>Parcle Size</span>
                            <i style="font-size:13px;">'.$parcel_size.'</i>
                       
                        </a>
                        <a href="#">

                            <span>Pickup Date</span>
                            <i style="font-size:13px;">'.$pickup_date.'</i>
                     
                      </a>
                        <a href="#">

                            <span>Pickup Time</span>
                            <i style="font-size:13px;">'.$pickup_time.'</i>
                      </a>
                        <a href="#">

                            <span>Orign City</span>
                            <i style="font-size:13px;">'.$origin_city.'</i>
                       </a>
                        <a href="#">

                            <span>Orign Address</span>
                            <i style="font-size:13px;">'.$origin_address.'</i>
                         </a>
                        <a href="#">

                            <span>Destination City</span>
                            <i style="font-size:13px;">'.$des_city.'</i>
                       </a>
                        <a href="#">

                            <span>Destination Address</span>
                            <i style="font-size:13px;">'.$des_address.'</i>
                      </a>
                        <a href="#">

                            <span>Sender</span>
                            <i style="font-size:13px;">'.$sender.'</i>
                       </a>
                        <a href="#">

                            <span>Reciver</span>
                            <i style="font-size:13px;">'.$receiver.'</i>
                        </a>
                         
                    </div>

                </div>
            </div>';


$detailsx =                '<div class="card brt border-0 card-style">
                <div class="content mb-0">
                    <div class="list-group list-custom-small">
                       
                        <a href="#">

                            <span>Status</span>
                            <i style="font-size:13px;">'.$status.'</i>
                         </a>
                        <a href="#">

                   
                            <span>Carier</span>
                            <i style="font-size:13px;">'.$carrier.'</i>
                   
                    </a>
                        <a href="#">


                            <span>Parcle Type</span>
                            <i style="font-size:13px;">'.$parcel_type.'</i>
                       
                        </a>
                        <a href="#">

                            <span>Parcle Size</span>
                            <i style="font-size:13px;">'.$parcel_size.'</i>
                       
                        </a>
                        <a href="#">

                            <span>Pickup Date</span>
                            <i style="font-size:13px;">'.$pickup_date.'</i>
                     
                      </a>
                        <a href="#">

                            <span>Pickup Time</span>
                            <i style="font-size:13px;">'.$pickup_time.'</i>
                      </a>
                        <a href="#">

                            <span>Orign City</span>
                            <i style="font-size:13px;">'.$origin_city.'</i>
                       </a>
                        <a href="#">

                            <span>Orign Address</span>
                            <i style="font-size:13px;">'.$origin_address.'</i>
                         </a>
                        <a href="#">

                            <span>Destination City</span>
                            <i style="font-size:13px;">'.$des_city.'</i>
                       </a>
                        <a href="#">

                            <span>Destination Address</span>
                            <i style="font-size:13px;">'.$des_address.'</i>
                      </a>
                       
                        <a href="#">

                            <span>Sender</span>
                            <i style="font-size:13px;">'.$sender.'</i>
                       </a>
                        <a href="#">

                            <span>Reciver</span>
                            <i style="font-size:13px;">'.$receiver.'</i>
                        </a>
                    </div>

                </div>
            </div>';
                
}
}
$output = array('details'=>$details, 'detailsx'=>$detailsx, 'status' => $status, 'carrier_id' => $carrier_id, 'delivery' => $delivery, 'des' => $des_address);
echo $_GET['callback']."(".json_encode($output).")"; //output JSON data
//mysql_close($con);
?>

