<?php

namespace leantime\domain\controllers {

    use leantime\core;
    use leantime\core\controller;
    use leantime\domain\repositories;
    use leantime\domain\services;
    use leantime\domain\models;
    use DateTime;
    use DateInterval;

    class editMilestone extends controller
    {
        private services\tickets $ticketService;
        private services\comments $commentsService;
        private services\projects $projectService;
        private repositories\tickets $ticketRepo;
        private repositories\projects $projectRepo;

        /**
         * init - initialize private variables
         *
         * @access public
         *
         */
        public function init(
            services\tickets $ticketService,
            services\comments $commentsService,
            services\projects $projectService,
            repositories\tickets $ticketRepo,
            repositories\projects $projectRepo
        ) {
            $this->ticketService = $ticketService;
            $this->commentsService = $commentsService;
            $this->projectService = $projectService;
            $this->ticketRepo = $ticketRepo;
            $this->projectRepo = $projectRepo;
        }

        /**
         * get - handle get requests
         *
         * @access public
         *
         */
        public function get($params)
        {
            if (isset($params['id'])) {
                //Delete comment
                if (isset($params['delComment']) === true) {
                    $commentId = (int)($params['delComment']);
                    $this->commentsService->deleteComment($commentId);

                    $this->tpl->setNotification($this->language->__("notifications.comment_deleted"), "success");
                }

                $milestone = $this->ticketRepo->getTicket($params['id']);
                $milestone = (object) $milestone;

                if (!isset($milestone->id)) {
                    $this->tpl->setNotification($this->language->__("notifications.could_not_find_milestone"), "error");
                    $this->tpl->redirect(BASE_URL . "/tickets/roadmap/");
                }

                //Ensure this ticket belongs to the current project
                if ($_SESSION["currentProject"] != $milestone->projectId) {
                    $this->projectService->changeCurrentSessionProject($milestone->projectId);
                    $this->tpl->redirect(BASE_URL . "/tickets/editMilestone/" . $milestone->id);
                }

                $comments = $this->commentsService->getComments('ticket', $params['id']);
            } else {
                $milestone = app()->make(models\tickets::class);
                $milestone->status = 3;

                $today = new DateTime();
                $milestone->editFrom = $today->format("Y-m-d");

                //Add 1 week
                $interval = new DateInterval('P1W');
                $next_week = $today->add($interval);

                $milestone->editTo = $next_week->format("Y-m-d");

                $comments = [];
            }

            $this->tpl->assign('statusLabels', $this->ticketService->getStatusLabels());
            $this->tpl->assign('comments', $comments);
            $allProjectMilestones = $this->ticketService->getAllMilestones($_SESSION['currentProject']);
            $this->tpl->assign('milestones', $allProjectMilestones);
            $this->tpl->assign('users', $this->projectRepo->getUsersAssignedToProject($_SESSION['currentProject']));
            $this->tpl->assign('milestone', $milestone);
            $this->tpl->displayPartial('tickets.milestoneDialog');
        }

        /**
         * post - handle post requests
         *
         * @access public
         *
         */
        public function post($params)
        {
            //If ID is set its an update
            if (isset($_GET['id']) && (int) $_GET['id'] > 0) {
                $params['id'] = (int)$_GET['id'];
                $milestone = $this->ticketRepo->getTicket($params['id']);

                if (isset($params['comment']) === true) {
                    $values = array(
                        'text' => $params['text'],
                        'date' => date("Y-m-d H:i:s"),
                        'userId' => ($_SESSION['userdata']['id']),
                        'moduleId' => $params['id'],
                        'father' => ($params['father'])
                    );


                    $messageId = $this->commentsService->addComment($values, 'ticket', $params['id'], $milestone);
                    $values['id'] = $messageId;
                    if ($messageId) {
                        $this->tpl->setNotification($this->language->__("notifications.comment_added_successfully"), "success");

                        $subject = $this->language->__("email_notifications.new_comment_milestone_subject");
                        $actual_link = BASE_URL . "/tickets/editMilestone/" . (int)$_GET['id'];
                        $message = sprintf($this->language->__("email_notifications.new_comment_milestone_message"), $_SESSION["userdata"]["name"]);


                        $notification = app()->make(models\notifications\notification::class);
                        $notification->url = array(
                            "url" => $actual_link,
                            "text" => $this->language->__("email_notifications.new_comment_milestone_cta")
                        );
                        $notification->entity = $values;
                        $notification->module = "comments";
                        $notification->projectId = $_SESSION['currentProject'];
                        $notification->subject = $subject;
                        $notification->authorId = $_SESSION['userdata']['id'];
                        $notification->message = $message;

                        $this->projectService->notifyProjectUsers($notification);
                    } else {
                        $this->tpl->setNotification($this->language->__("notifications.problem_saving_your_comment"), "error");
                    }

                    $this->tpl->redirect(BASE_URL . "/tickets/editMilestone/" . $params['id']);
                }

                if (isset($params['headline']) === true) {
                    if ($this->ticketService->quickUpdateMilestone($params) == true) {
                        $this->tpl->setNotification($this->language->__("notification.milestone_edited_successfully"), "success");

                        $subject = $this->language->__("email_notifications.milestone_update_subject");
                        $actual_link = BASE_URL . "/tickets/editMilestone/" . (int)$_GET['id'];
                        $message = sprintf($this->language->__("email_notifications.milestone_update_message"), $_SESSION["userdata"]["name"]);

                        $notification = app()->make(models\notifications\notification::class);
                        $notification->url = array(
                            "url" => $actual_link,
                            "text" => $this->language->__("email_notifications.milestone_update_cta")
                        );
                        $notification->entity = $params;
                        $notification->module = "tickets";
                        $notification->projectId = $_SESSION['currentProject'];
                        $notification->subject = $subject;
                        $notification->authorId = $_SESSION['userdata']['id'];
                        $notification->message = $message;

                        $this->projectService->notifyProjectUsers($notification);

                        $this->tpl->redirect(BASE_URL . "/tickets/editMilestone/" . $params['id']);
                    } else {
                        $this->tpl->setNotification($this->language->__("notification.saving_milestone_error"), "error");
                        $this->tpl->redirect(BASE_URL . "/tickets/editMilestone/" . $params['id']);
                    }
                }

                $this->tpl->redirect(BASE_URL . "/tickets/editMilestone/" . $params['id']);
            } else {
                $result = $this->ticketService->quickAddMilestone($params);

                if (is_numeric($result)) {
                    $params["id"] = $result;

                    $this->tpl->setNotification($this->language->__("notification.milestone_created_successfully"), "success");

                    $subject = $this->language->__("email_notifications.milestone_created_subject");
                    $actual_link = BASE_URL . "/tickets/editMilestone/" . $result;
                    $message = sprintf($this->language->__("email_notifications.milestone_created_message"), $_SESSION["userdata"]["name"]);

                    $notification = app()->make(models\notifications\notification::class);
                    $notification->url = array(
                        "url" => $actual_link,
                        "text" => $this->language->__("email_notifications.milestone_created_cta")
                    );
                    $notification->entity = $params;
                    $notification->module = "tickets";
                    $notification->projectId = $_SESSION['currentProject'];
                    $notification->subject = $subject;
                    $notification->authorId = $_SESSION['userdata']['id'];
                    $notification->message = $message;

                    $this->projectService->notifyProjectUsers($notification);

                    $this->tpl->redirect(BASE_URL . "/tickets/editMilestone/" . $result);
                } else {
                    $this->tpl->setNotification($this->language->__("notification.saving_milestone_error"), "error");
                    $this->tpl->redirect(BASE_URL . "/tickets/editMilestone/");
                }
            }

            $this->tpl->assign('statusLabels', $this->ticketService->getStatusLabels());
            $this->tpl->assign('milestone', (object) $params);
            $this->tpl->displayPartial('tickets.milestoneDialog');
        }

        /**
         * put - handle put requests
         *
         * @access public
         *
         */
        public function put($params)
        {
        }

        /**
         * delete - handle delete requests
         *
         * @access public
         *
         */
        public function delete($params)
        {
        }
    }

}
