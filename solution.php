<?php
// Vitali Kavaleuski, 7vitalij@gmail.com, http://vitali-kavaleuski.com

/**
 * Returns payload array by a request string and a secret
 *
 * @param $request
 * @param $secret
 * @return bool|array
 */
function parse_request($request, $secret)
{
    // undo changes from make_request method one by one
    $request = strtr($request, '-_', '+/');
    $parameters = explode('.', $request);
    if (!isset($parameters[0]) || !isset($parameters[1])) {
        return false;
    }

    $payload = base64_decode($parameters[1]);
    $payload = json_decode($payload, true);
    if ($payload === null) {
        // Return false when $payload is not valid json string
        return false;
    }

    $calculatedSignature = hash_hmac('sha256', json_encode($payload), $secret);
    $providedSignature = base64_decode($parameters[0]);
    if ($calculatedSignature != $providedSignature) {
        // Return false when calculated and provided signatures don't match
        return false;
    }

    return (array)$payload;
}

/**
 * Returns dates which have at least $n scores
 *
 * @param $pdo
 * @param $n
 * @return array
 */
function dates_with_at_least_n_scores($pdo, $n)
{
    $n = (int)$n;
    $sql = "
      SELECT `date`
      FROM scores
      WHERE 1
      GROUP BY `date`
      HAVING COUNT(*) >= $n
      ORDER BY `date` DESC
    ";

    return (array)$pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Returns all users with top score on a date
 *
 * @param $pdo
 * @param $date
 * @return array
 */
function users_with_top_score_on_date($pdo, $date)
{
    $sql = "
      SELECT `user_id`
      FROM scores
      WHERE `date` = :date AND `score` = (
        /* Get top score on a date */
        SELECT MAX(`score`)
        FROM scores
        WHERE `date` = :date
        GROUP BY `date`
      )
      ORDER BY `user_id` ASC
    ";

    $sth = $pdo->prepare($sql); // Prepares a statement for execution and returns a statement object
    $sth->execute([':date' => $date]); // Executes a prepared statement with provided input parameters

    return (array)$sth->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Returns dates when $user_id was in top $n results
 *
 * @param $pdo
 * @param $user_id
 * @param $n
 * @return mixed
 */
function dates_when_user_was_in_top_n($pdo, $user_id, $n)
{
    $sql = "
      SELECT sc1.`date`
      FROM scores AS sc1
      /* find a user by a user_id and a score from 'top scores array' */
      WHERE sc1.`user_id` = :user_id AND sc1.`score` IN (
        /* Select top :limit scores on a date */
        SELECT sc2.`score`
        FROM scores AS sc2 
        WHERE sc2.`date` = sc1.`date` 
        ORDER BY sc2.`score` DESC
        LIMIT :limit
      )
      ORDER BY sc1.`date` DESC
    ";
    $sth = $pdo->prepare($sql);
    $sth->execute([':user_id' => (int)$user_id, ':limit' => (int)$n]);

    return (array)$sth->fetchAll(PDO::FETCH_COLUMN);
}
