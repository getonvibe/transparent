<?php

/**
 * editCanvasItem class - Generic canvas controller / Edit Canvas Item
 */

namespace leantime\domain\controllers\canvas {

    use leantime\core\controller;
    use leantime\domain\repositories;
    use leantime\domain\services;
    use leantime\domain\models;

    class editCanvasItem extends controller
    {
        /**
         * Constant that must be redefined
         */
        protected const CANVAS_NAME = '??';

        private services\tickets $ticketService;
        private services\projects $projectService;
        private repositories\comments $commentsRepo;
        private object $canvasRepo;

        /**
         * init - initialize private variables
         *
         * @access public
         *
         */
        public function init(
            serivces\tickets $ticketService,
            services\projects $projectService,
            repositories\comments $commentsRepo
        ) {
            $this->ticketService = $ticketService;
            $this->commentsRepo = $commentsRepo;
            $this->projectService = $projectService;

            $canvasRepoName = "leantime\\domain\\repositories\\" . static::CANVAS_NAME . 'canvas';
            $this->canvasRepo = app()->make($canvasRepoName);
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
                // Delete comment
                if (isset($params['delComment'])) {
                    $commentId = (int)($params['delComment']);
                    $this->commentsRepo->deleteComment($commentId);
                    $this->tpl->setNotification($this->language->__('notifications.comment_deleted'), 'success');
                }

                // Delete milestone relationship
                if (isset($params['removeMilestone'])) {
                    $this->canvasRepo->patchCanvasItem($params['id'], array('milestoneId' => ''));
                    $this->tpl->setNotification($this->language->__('notifications.milestone_detached'), 'success');
                }

                $canvasItem = $this->canvasRepo->getSingleCanvasItem($params['id']);

                if($canvasItem) {
                    $comments = $this->commentsRepo->getComments(
                        static::CANVAS_NAME . 'canvas' . 'item',
                        $canvasItem['id']
                    );
                    $this->tpl->assign(
                        'numComments',
                        $this->commentsRepo->countComments(
                            static::CANVAS_NAME . 'canvas' . 'item',
                            $canvasItem['id']
                        )
                    );
                }else{
                    $this->tpl->displayPartial('errors.error404');
                    exit();
                }
            } else {
                if (isset($params['type'])) {
                    $type = strip_tags($params['type']);
                } else {
                    $type = array_key_first($this->canvasRepo->elementLabels);
                }

                $canvasItem = array(
                    'id' => '',
                    'box' => $type,
                    'description' => '',
                    'status' => array_key_first($this->canvasRepo->getStatusLabels()),
                    'relates' => array_key_first($this->canvasRepo->getRelatesLabels()),
                    'assumptions' => '',
                    'data' => '',
                    'conclusion' => '',
                    'milestoneHeadline' => '',
                    'milestoneId' => ''
                );

                $comments = [];
            }

            $this->tpl->assign('comments', $comments);

            $this->tpl->assign('milestones', $this->ticketService->getAllMilestones($_SESSION['currentProject']));
            $this->tpl->assign('canvasItem', $canvasItem);
            $this->tpl->assign('canvasIcon', $this->canvasRepo->getIcon());
            $this->tpl->assign('relatesLabels', $this->canvasRepo->getRelatesLabels());
            $this->tpl->assign('canvasTypes', $this->canvasRepo->getCanvasTypes());
            $this->tpl->assign('statusLabels', $this->canvasRepo->getStatusLabels());
            $this->tpl->assign('dataLabels', $this->canvasRepo->getDataLabels());
            $this->tpl->displayPartial(static::CANVAS_NAME . 'canvas' . '.canvasDialog');
        }

        /**
         * post - handle post requests
         *
         * @access public
         *
         */
        public function post($params)
        {

            if (isset($params['changeItem'])) {
                if (isset($params['itemId']) && !empty($params['itemId'])) {
                    if (isset($params['description']) && !empty($params['description'])) {
                        $currentCanvasId = (int)$_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'];

                        $canvasItem = array(
                            'box' => $params['box'],
                            'author' => $_SESSION['userdata']['id'],
                            'description' => $params['description'],
                            'status' => $params['status'],
                            'relates' => $params['relates'],
                            'assumptions' => $params['assumptions'],
                            'data' => $params['data'],
                            'conclusion' => $params['conclusion'],
                            'itemId' => $params['itemId'],
                            'canvasId' => $currentCanvasId,
                            'milestoneId' => $params['milestoneId'],
                            'dependentMilstone' => '',
                            "id" => $params['itemId']
                        );

                        if (isset($params['newMilestone']) && $params['newMilestone'] != '') {
                            $params['headline'] = $params['newMilestone'];
                            $params['tags'] = '#ccc';
                            $params['editFrom'] = date('Y-m-d');
                            $params['editTo'] = date('Y-m-d', strtotime('+1 week'));
                            $params['dependentMilestone'] = '';
                            $id = $this->ticketService->quickAddMilestone($params);

                            if ($id !== false) {
                                $canvasItem['milestoneId'] = $id;
                            }
                        }
                        if (isset($params['existingMilestone']) && $params['existingMilestone'] != '') {
                            $canvasItem['milestoneId'] = $params['existingMilestone'];
                        }

                        $this->canvasRepo->editCanvasItem($canvasItem);

                        $comments = $this->commentsRepo->getComments(static::CANVAS_NAME . 'canvas' . 'item', $params['itemId']);
                        $this->tpl->assign('numComments', $this->commentsRepo->countComments(
                            static::CANVAS_NAME . 'canvas' . 'item',
                            $params['itemId']
                        ));
                        $this->tpl->assign('comments', $comments);

                        $this->tpl->setNotification($this->language->__('notifications.canvas_item_updates'), 'success');

                        $subject = $this->language->__('email_notifications.canvas_board_edited');
                        $actual_link = BASE_URL . '/' . static::CANVAS_NAME . 'canvas' . '/editCanvasItem/' . (int)$params['itemId'];
                        $message = sprintf(
                            $this->language->__('email_notifications.canvas_item_update_message'),
                            $_SESSION['userdata']['name'],
                            $canvasItem['description']
                        );

                        $notification = app()->make(models\notifications\notification::class);
                        $notification->url = array(
                            "url" => $actual_link,
                            "text" => $this->language->__('email_notifications.canvas_item_update_cta')
                        );
                        $notification->entity = $canvasItem;
                        $notification->module = static::CANVAS_NAME . 'canvas';
                        $notification->projectId = $_SESSION['currentProject'];
                        $notification->subject = $subject;
                        $notification->authorId = $_SESSION['userdata']['id'];
                        $notification->message = $message;

                        $this->projectService->notifyProjectUsers($notification);

                        $closeModal = '';
                        if(isset($_POST['submitAction']) && $_POST['submitAction'] == "closeModal"){
                            $closeModal = "?closeModal=true";
                        }

                        $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas' . '/editCanvasItem/' . $params['itemId'].$closeModal);
                    } else {
                        $this->tpl->setNotification($this->language->__('notification.please_enter_title'), 'error');
                    }
                } else {
                    if (isset($_POST['description']) && !empty($_POST['description'])) {
                        $currentCanvasId = (int)$_SESSION['current' . strtoupper(static::CANVAS_NAME) . 'Canvas'];

                        $canvasItem = array(
                            'box' => $params['box'],
                            'author' => $_SESSION['userdata']['id'],
                            'description' => $params['description'],
                            'status' => $params['status'],
                            'relates' => $params['relates'],
                            'assumptions' => $params['assumptions'],
                            'data' => $params['data'],
                            'conclusion' => $params['conclusion'],
                            'canvasId' => $currentCanvasId
                        );

                        $id = $this->canvasRepo->addCanvasItem($canvasItem);
                        $canvasTypes = $this->canvasRepo->getCanvasTypes();

                        $this->tpl->setNotification($canvasTypes[$params['box']]['title'] . ' successfully created', 'success');

                        $subject = $this->language->__('email_notifications.canvas_board_item_created');
                        $actual_link = BASE_URL . '/' . static::CANVAS_NAME . 'canvas' . '/editCanvasItem/' . (int)$params['itemId'];
                        $message = sprintf(
                            $this->language->__('email_notifications.canvas_item_created_message'),
                            $_SESSION['userdata']['name'],
                            $canvasItem['description']
                        );

                        $notification = app()->make(models\notifications\notification::class);
                        $notification->url = array(
                            "url" => $actual_link,
                            "text" => $this->language->__('email_notifications.canvas_item_update_cta')
                        );

                        $notification->entity = $canvasItem;
                        $notification->module = static::CANVAS_NAME . 'canvas';
                        $notification->projectId = $_SESSION['currentProject'];
                        $notification->subject = $subject;
                        $notification->authorId = $_SESSION['userdata']['id'];
                        $notification->message = $message;

                        $this->projectService->notifyProjectUsers($notification);

                        $this->tpl->setNotification($this->language->__('notification.element_created'), 'success');

                        $closeModal = '';
                        if(isset($_POST['submitAction']) && $_POST['submitAction'] == "closeModal"){
                            $closeModal = "?closeModal=true";
                        }

                        $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas' . '/editCanvasItem/' . $id.$closeModal);
                    } else {
                        $this->tpl->setNotification($this->language->__('notification.please_enter_title'), 'error');
                    }
                }
            }

            if (isset($params['comment'])) {
                $values = array(
                    'text' => $params['text'],
                    'date' => date('Y-m-d H:i:s'),
                    'userId' => ($_SESSION['userdata']['id']),
                    'moduleId' => $_GET['id'],
                    'commentParent' => ($params['father'])
                );

                $commentId = $this->commentsRepo->addComment($values, static::CANVAS_NAME . 'canvas' . 'item');
                $this->tpl->setNotification($this->language->__('notifications.comment_create_success'), 'success');
                $values['id'] = $commentId;

                $subject = $this->language->__('email_notifications.canvas_board_comment_created');
                $actual_link = BASE_URL . '/' . static::CANVAS_NAME . 'canvas' . '/editCanvasItem/' . (int)$_GET['id'];
                $message = sprintf(
                    $this->language->__('email_notifications.canvas_item__comment_created_message'),
                    $_SESSION['userdata']['name']
                );

                $notification = app()->make(models\notifications\notification::class);
                $notification->url = array(
                    "url" => $actual_link,
                    "text" => $this->language->__('email_notifications.canvas_item_update_cta')
                );
                $notification->entity = $values;
                $notification->module = static::CANVAS_NAME . 'canvas';
                $notification->projectId = $_SESSION['currentProject'];
                $notification->subject = $subject;
                $notification->authorId = $_SESSION['userdata']['id'];
                $notification->message = $message;

                $this->projectService->notifyProjectUsers($notification);

                $this->tpl->redirect(BASE_URL . '/' . static::CANVAS_NAME . 'canvas' . '/editCanvasItem/' . $_GET['id']);
            }

            $this->tpl->assign('milestones', $this->ticketService->getAllMilestones($_SESSION['currentProject']));
            $this->tpl->assign('canvasTypes', $this->canvasRepo->getCanvasTypes());
            $this->tpl->assign('statusLabels', $this->canvasRepo->getStatusLabels());
            $this->tpl->assign('relatesLabels', $this->canvasRepo->getRelatesLabels());
            $this->tpl->assign('dataLabels', $this->canvasRepo->getDataLabels());
            if (isset($_GET['id'])) {
                $comments = $this->commentsRepo->getComments(static::CANVAS_NAME . 'canvas' . 'item', $_GET['id']);
                $this->tpl->assign('canvasItem', $this->canvasRepo->getSingleCanvasItem($_GET['id']));
            } else {
                $value = array(
                    'id' => '',
                    'box' => $params['box'],
                    'author' => $_SESSION['userdata']['id'],
                    'description' => '',
                    'status' => array_key_first($this->canvasRepo->getStatusLabels()),
                    'relates' => array_key_first($this->canvasRepo->getRelatesLabels()),
                    'assumptions' => '',
                    'data' => '',
                    'conclusion' => '',
                    'milestoneHeadline' => '',
                    'milestoneId' => ''
                );
                $comments = array();
                $this->tpl->assign('canvasItem', $value);
            }
            $this->tpl->assign('comments', $comments);
            $this->tpl->displayPartial(static::CANVAS_NAME . 'canvas' . '.canvasDialog');
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
