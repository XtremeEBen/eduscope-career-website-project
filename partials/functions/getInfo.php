<?php

// Start the session
session_start();

/*
    getInfo is a common service script for all requests that replies with a JSON query based on the arguments provided
    
    BASIC STATUS RESPONSE (Doesn't apply to some parts)
    Initlaize response array to be sent as JSON, each response will have ['status'] attribute to notify the frontend about any errors
    The response['status'] of type int attribute will mean the following
    0 : user not logged in
    1 : user logged in, but no profile details found, first time login
    2 : user logged in, profile details found
    
    TYPE: list
    Alternatively, you can also get a list of things without concering the user id
    getInfo.php?type=list&from=tableName
    type : list | This will tell the script that you want a list of things
    from : <table name> | This will be the table you would like to get 
    
    TYPE: profile
    You can also get info of another user by setting the type as profile and the user as the username of the profile
    for example..
    getInfo.php?type=profile&user=username
    type : profile | This will tell the script to get profile details of a specified user
    user : <username> | The user name to look at
    
    TYPE: relation
    You can check the relation between the user and the mentor (The user recieving the mentor request)
    for example..
    getInfo.php?type=relation&receiver=id
    type : relation | This will tell the script to look for what relation the 2 user's have
    receiver : <id> | The user_id of the reciever
    Response is sent as..
    RelationStatus : (0|1|2|3) | 
        2 means user is not a mentor
        1 means user is already mentor, 
        3 means request pending, 
        0 means user is sending request to self
    relation : <string> | The message to be sent depending on the relation, 3 cases user sends to themselves, or the other user is already a mentor, or the other
    user's request is pending, or the sending user is not linked with the receiving user.
    oppositeRelationStatus : (0|1|2) | 
        0 : user is not related to them
        1 : user is the user who they mentor
        2 : user is a user who would like to be mentored and has sent a request
    oppositeRelation : <string> | The string will be based on the relation status

    TYPE: mymentors
    This will retrive a list of mentors of the loggedin user
    URL: getInfo.php?type=mymentors
    RESPONSE: 
    status : (0|1) | 0 for failure and 1 for success
    mentors : <array> | An array of mentors containg JSON objects with properties
       - user_username
       - userDetail_firstName
       - userDetail_lastName
    To access ith element, data.mentors[i].<property>
       
    TYPE: myusers
    This will retrive a list of users metored by the loggedin user
    URL: getInfo.php?type=myusers
    RESPONSE: 
    status : (0|1) | 0 for failure and 1 for success
    mentors : <array> | An array of users containg JSON objects with properties
       - user_username
       - userDetail_firstName
       - userDetail_lastName
    To access ith element, data.users[i].<property>
    
    TYPE: myrequests
    This will give a list of users that have sent the logged in user a mentor request.
    URL: getInfo.php?type=myrequests
    RESPONSE:
    status : (0|1) | 0 for failure and 1 for success
    requests : <array> | An array of users containg JSON objects with properties
        - user_username
        - userDetail_firstName
        - userDetail_lastName
    To access ith element, data.requests[i].<property>
    
    TYPE:myquals
    This will give a list of school and uni qualification that the user has in 2 different arrays
    URL: getInfo.php?type=myquals
    RESPONSE:
    status: (0|1) | 0 for failure and 1 for success
    school: <array> | An array of school qualifications
        - school_name
        - grad_year
        - qualification
    school_subs: <array> | An array of subjects the user did in school
        - qualification
        - subject_name
        - score
    uni: <array> | An array of university qualifications
        - uni_name
        - name | name of the qualification
        - short_title
        - type
        - subject_name
        - grad_year
    To access the ith element in JS, use data.school[i].id or data.uni[i].id
    
    TYPE:myscores
    This will give score for the user's subjects to know where his acadmeic strength is
    URL: getInfo.php?type=myscores
    RESPONSE:
    status: (0|1) | 0 for failure and 1 for success
    scores: <array> | An array of scores for the subjects including most of the data
        - subject_name
        - score | the ucas score
        - universities | no. universities the user has attended for this specific subject
        - jobs | no. of jobs done by the user related to that subject
*/

$response = array();

if(isset($_SESSION['loggedin_user']) == false || checkType($_GET['type']) == false){
    
    $response['status'] = 0;
    $response['error'] = 'User not logged in or type check failed';
    send_response($response);

}else{
    
    require_once 'config.php';
    
    // Get the type of database that needs to be accessed, eg. user, userDetailDB.. etc. 
    $type = $_GET['type'];
    $user_id = $_SESSION['loggedin_user'];
    
    // Variables
    $sql;       // The SQL Query
    $result;    // Result for the SQL query
    
    // Set response
    $response['status'] = 1;
    
    // user contains sensitive information and requires precaution
    switch($type){
        case 'user':
            $sql = 'SELECT user_username, user_email FROM user WHERE user_id = '.$user_id;
            // Get the results
            $result = $mysqli->query($sql);
            $response[$type] = $result->fetch_assoc();
            send_response($response);
            break;
        case 'userDetail':
            $sql = 'SELECT * FROM '.$type.' WHERE '.$type.'_id = '.$user_id;
            // Get the results
            $result = $mysqli->query($sql);
            $response[$type] = $result->fetch_assoc();
            send_response($response);
            break;
        case 'list':
            $from = $_GET['from'];
            switch($from){
                case 'qualifications':
                case 'subjects':
                    $sql = 'SELECT * FROM '.$from;
                    $i = 0;
                    $result = $mysqli->query($sql);
                    while($row = $result->fetch_assoc()){
                        $response[$from][$i] = $row;
                        $i++;
                    }
                    send_response($response);
                    break;
                default:
                        $response['status'] = 0;
                        send_response($response);
            }
            break;
        case 'profile':
            $user = $_GET['user'];
            // Check if the username is alphanumeric
            if(preg_match("/[^a-zA-Z0-9]/i", $user, $match)){
                $response['status'] = 0;
                send_response($response);
            }else{
                // First we get the user_id
                $sql = 'SELECT user_id FROM user WHERE user_username = "'.$user.'"';
                $result = $mysqli->query($sql);
                $user_id = $result->fetch_assoc(); // fetches as an array
                // Then we get the user detail of the user id, using user_id[key]
                $sql = 'SELECT * FROM userDetail WHERE userDetail_id = '.$user_id['user_id'];
                $result = $mysqli->query($sql);
                // Store and send response
                $response[$type] = $result->fetch_assoc();
                send_response($response);
            }
            break;
        case 'relation';
            $receiver_id = $_GET['receiver'];
            // Check if the receiver is numeric
            if(preg_match("/[^0-9]/i", $receiver_id, $match)){
                $response['status'] = 0;
                send_response($response);
            }else{
                // First we get the user_id
                $sql = 'SELECT user_id FROM user WHERE user_id = "'.$receiver_id.'"';
                $result = $mysqli->query($sql);
                $receiver_id = $result->fetch_assoc(); // fetches as an array
                if($result->num_rows > 0){
                    if($receiver_id['user_id'] == $user_id){
                        $response['relationStatus'] = 0;
                        $response['relation'] = 'Can\'t send request to self';
                    }else{
                        // Check for relation status
                        $sql = 'SELECT * FROM request_pending WHERE fk_sender_user_id = '.$user_id.' AND fk_acceptor_user_id = '.$receiver_id['user_id'];
                        $result = $mysqli->query($sql);
                        if($result->num_rows == 0){
                            $sql = 'SELECT * FROM userMentor WHERE fk_user_id = '.$user_id.' AND fk_mentor_id = '.$receiver_id['user_id'];
                            $result = $mysqli->query($sql);
                            if($result->num_rows > 0){
                                $response['relationStatus'] = 1;
                                $response['relation'] = 'This User is your mentor';
                            }else{
                                $response['relationStatus'] = 2;
                                $response['relation'] = 'This User is not your mentor';
                            }
                        }else{
                            $response['relationStatus'] = 3;
                            $response['relation'] = 'Mentor request pending';
                        }
                        // Check for opposite relation status (i.e. the user's relation with the logged in user)
                        $sql = 'SELECT * FROM request_pending WHERE fk_acceptor_user_id = '.$user_id.' AND fk_sender_user_id = '.$receiver_id['user_id'];
                        $result = $mysqli->query($sql);
                        if($result->num_rows == 0){
                            // check if the user is already a mentor
                            $sql = 'SELECT * FROM userMentor WHERE fk_mentor_id = '.$user_id.' AND fk_user_id = '.$receiver_id['user_id'];
                            $result = $mysqli->query($sql);
                            if($result->num_rows == 0){
                                // User is not related
                                $response['oppositeRelationStatus'] = 0;
                                $response['oppositeRelation'] = 'This user is not related at all';
                            }else{
                                // User is already being mentored
                                $response['oppositeRelationStatus'] = 1;
                                $response['oppositeRelation'] = 'This user is being mentored by you';
                            }
                        }else{
                            // User has got a request from the user, awaiting confirmation
                            $response['oppositeRelationStatus'] = 2;
                            $response['oppositeRelation'] = 'This user has sent you a mentor request';
                        }
                    }
                }else{
                    $response['status'] = 0;
                }
            }
            send_response($response);
            break;
        case 'mymentors':
            $sql = 'SELECT user_username, userDetail_firstName, userDetail_lastName FROM user,userDetail,userMentor WHERE fk_mentor_id = user_id AND fk_mentor_id = userDetail_id AND fk_user_id = '.$user_id;
            $result = $mysqli->query($sql);
            $i = 0;
            while($row = $result->fetch_assoc()){
                $response['mentors'][$i] = $row;
                $i++;
            }
            send_response($response);
            break;
        case 'myusers':
            $sql = 'SELECT user_username, userDetail_firstName, userDetail_lastName FROM user,userDetail,userMentor WHERE fk_user_id = user_id AND fk_user_id = userDetail_id AND fk_mentor_id = '.$user_id;
            $result = $mysqli->query($sql);
            $i = 0;
            while($row = $result->fetch_assoc()){
                $response['users'][$i] = $row;
                $i++;
            }
            send_response($response);
            break;
        case 'myrequests':
            $sql = 'SELECT user_username, userDetail_firstName, userDetail_lastName FROM user,userDetail,request_pending WHERE fk_sender_user_id = user_id AND fk_sender_user_id = userDetail_id AND fk_acceptor_user_id = '.$user_id;
            $result = $mysqli->query($sql);
            $i = 0;
            while($row = $result->fetch_assoc()){
                $response['requests'][$i] = $row;
                $i++;
            }
            send_response($response);
            break;
        case 'myquals':
            $sql_school = 'SELECT school_name,id,grad_year,qualification FROM user_school_qualification WHERE fk_user_id = '.$user_id;
            $sql_school_subs = 'SELECT fk_user_school_qualification_id as school_qualification_id,fk_subject_id as subject_id,qualification,subject_name,score FROM user_school_qualification,user_school_qualification_subjects,subjects WHERE user_school_qualification.id = fk_user_school_qualification_id AND subject_id = fk_subject_id AND fk_user_id = '.$user_id;
            $sql_uni = 'SELECT universities.id as uni_id, universities.name as uni_name,qualifications.id as qual_id, qualifications.name,qualifications.short_title,qualifications.type,subject_name,grad_year FROM user_uni_qualification,universities,qualifications,subjects WHERE subject_id = fk_subject_id AND universities.id = fk_uni_id AND qualifications.id = fk_qualification_id AND fk_user_id = '.$user_id;
            $result_school = $mysqli->query($sql_school);
            $result_school_subs = $mysqli->query($sql_school_subs);
            $result_uni = $mysqli->query($sql_uni);
            if($result_school != false && $result_school_subs != false && $result_uni != false){
                $i = 0;
                $j = 0;
                $k = 0;
                while($row = $result_school->fetch_assoc()){
                    $response['school'][$i++] = $row;
                }
                while($row = $result_school_subs->fetch_assoc()){
                    $response['school_subs'][$j++] = $row;
                }
                while($row = $result_uni->fetch_assoc()){
                    $response['uni'][$k++] = $row;
                }
                $response['status'] = 1;
                send_response($response);
            }else{
                $response['status'] = 0;
                $response['error'] = 'Error retriving qualifications';
            }
            break;
        case 'myscores':
            $sql = 'select subject_name,score,universities,jobs from subjects,user_subject_score where subject_id = fk_subject_id and fk_user_id = '.$user_id;
            $result = $mysqli->query($sql);
            if($result != false){
                $i = 0;
                while($row = $result->fetch_assoc()){
                    $response['scores'][$i++] = $row;
                }
                $response['status'] = 1;
                send_response($response);
            }else{
                $response['status'] = 0;
                $response['error'] = 'Error getting user stats';
            }
        break;
        case 'myjobs':
            $sql = 'select fk_job_id as job_id,company_name,company_location,start_year,end_year,jobs.title,subject_name from jobs,subjects,user_jobs where jobs.id = fk_job_id and subject_id = fk_subject_id and fk_user_id = '.$user_id;
            $result = $mysqli->query($sql);
            if($result != false){
                $i = 0;
                while($row = $result->fetch_assoc()){
                    $response['jobs'][$i++] = $row;
                }
                $response['status'] = 1;
                send_response($response);
            }else{
                $response['status'] = 0;
                $response['error'] = 'Error getting user jobs';
            }
        break;
        case 'myhistory':
            $sql = 'select grad_year as year, qualification as qualification, school_name as name from user_school_qualification where fk_user_id = '.$user_id.' union select grad_year as year,qualifications.name as qualification,universities.name as name from user_uni_qualification,universities,qualifications where qualifications.id = fk_qualification_id and universities.id = fk_uni_id and fk_user_id = '.$user_id.' union select start_year as year,jobs.title as qualification,company_name as name from user_jobs,jobs where jobs.id = fk_job_id and fk_user_id = '.$user_id.' order by year';
            $result = $mysqli->query($sql);
            if($result != false){
                $i = 0;
                while($row = $result->fetch_assoc()){
                    $response['history'][$i++] = $row;
                }
                $response['status'] = 1;
                send_response($response);
            }else{
                $response['status'] = 0;
                $response['error'] = 'Error getting user jobs';
            }
        break;
        case 'mybest':
            $sql = 'select * from user_subject_score where fk_user_id = '.$user_id;
            $result = $mysqli->query($sql);
            $best_subject_id = getBestSubject($result);
            $result = $mysqli->query($sql);
            $t_score = getTotalScore($result);
            $sql_sub = 'select subject_name from subjects where subject_id = '.$best_subject_id;
            $result_sub = $mysqli->query($sql_sub);
            $response['subject'] = $result_sub->fetch_assoc()['subject_name'];
            $sql_quals = 'select name,short_title from qualifications where fk_subject_id = '.$best_subject_id;
            $result_quals = $mysqli->query($sql_quals);
            $i = 0;
            while($row = $result_quals->fetch_assoc()){
                $response['qualifications'][$i] = $row;
                $i++;
            }
            $sql_jobs = 'select title from jobs where fk_subject_id = '.$best_subject_id;
            $result_jobs = $mysqli->query($sql_jobs);
            $i = 0;
            while($row = $result_jobs->fetch_assoc()){
                $response['jobs'][$i] = $row;
                $i++;
            }
            $sql_universities = 'select rank,name from universities where entry between '.($t_score-50).' and '.($t_score+50);
            $result_unis = $mysqli->query($sql_universities);
            $i = 0;
            while($row = $result_unis->fetch_assoc()){
                $response['universities'][$i] = $row;
                $i++;
            }
            send_response($response);
        default: 
        $sql = 'SELECT * FROM '.$type.' WHERE fk_user_id = '.$user_id;
    }

}

$mysqli->close();

function getBestSubject($r){
    $t_score = 0;
    $best_subject = 0; // for jobs and quals
    $best_score = 0;
    $i = 0;
    while($row = $r->fetch_assoc()){
        $t_score += $row['score']+($row['universities']*500)+($row['jobs']*750);
        if ($t_score > $best_score){
            $best_score = $t_score;
            $best_subject = $row['fk_subject_id'];
        }
        $i++;
    }
    return $best_subject;
}

function getTotalScore($r){
    $t_score = 0; // for universitie
    $i = 0;
    while($row = $r->fetch_assoc()){
        $t_score += $row['score'];
        $i++;
    }
    return $t_score;
}


/*  This function takes a response and simply echos it for the app.js to read
    $data : Array - array $response from the php file 
*/
function send_response($data){
    header('Content-type: application/json');
    echo json_encode($data);
}

function checkType($type){
    if(preg_match("/^(userDetail|user|list|profile|relation|mymentors|myusers|myrequests|myquals|myscores|myjobs|myhistory|mybest)$/", $type, $match)){
        return true;
    }else{
        return false;
    }
}