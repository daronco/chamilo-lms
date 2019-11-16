<?php
/* For licensing terms, see /license.txt */

namespace Chamilo\PluginBundle\MigrationMoodle\Loader;

use Chamilo\PluginBundle\MigrationMoodle\Interfaces\LoaderInterface;

/**
 * Class LpQuizzesLoader.
 *
 * @package Chamilo\PluginBundle\MigrationMoodle\Loader
 */
class LpQuizzesLoader implements LoaderInterface
{
    /**
     * Load the data and return the ID inserted.
     *
     * @param array $incomingData
     *
     * @throws \Doctrine\DBAL\DBALException
     *
     * @return int
     */
    public function load(array $incomingData)
    {
        $exercise = new \Exercise($incomingData['c_id']);
        $exercise->updateTitle(\Exercise::format_title_variable($incomingData['item_title']));
        $exercise->updateDescription($incomingData['item_content']);
        $exercise->updateAttempts(0);
        $exercise->updateFeedbackType(0);
        $exercise->updateType(2);
        $exercise->setRandom(0);
        $exercise->updateRandomAnswers(0);
        $exercise->updateResultsDisabled(0);
        $exercise->updateExpiredTime(0);
        $exercise->updateTextWhenFinished('');
        $exercise->updateDisplayCategoryName(1);
        $exercise->updatePassPercentage(0);
        $exercise->setQuestionSelectionType(1);
        $exercise->setHideQuestionTitle(0);
        $exercise->sessionId = 0;
//        $exercise->setPageResultConfiguration($incomingData);
        $exercise->start_time = null;
        $exercise->end_time = null;

        $quizId = $exercise->save();

        \Database::getManager()
            ->createQuery('UPDATE ChamiloCourseBundle:CLpItem i SET i.path = :path WHERE i.iid = :id')
            ->setParameters(['path' => $quizId, 'id' => $incomingData['item_id']])
            ->execute();

        return $quizId;
    }
}
