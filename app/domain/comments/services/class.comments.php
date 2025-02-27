<?php

namespace leantime\domain\services {

    use leantime\core;
    use leantime\domain\models\notifications\notification;
    use leantime\domain\repositories;
    use leantime\domain\services;

    class comments
    {
        private repositories\comments $commentRepository;
        private services\projects $projectService;
        private core\language $language;

        public function __construct(
            repositories\comments $commentRepository,
            services\projects $projectService,
            core\language $language
        ) {
            $this->commentRepository = $commentRepository;
            $this->projectService = $projectService;
            $this->language = $language;
        }

        public function getComments($module, $entityId, $commentOrder = 0)
        {
            return $this->commentRepository->getComments($module, $entityId, "", $commentOrder);
        }

        public function addComment($values, $module, $entityId, $entity)
        {


            if (isset($values['text']) && $values['text'] != '' && isset($values['father']) && isset($module) &&  isset($entityId) &&  isset($entity)) {
                $mapper = array(
                    'text' => $values['text'],
                    'date' => date("Y-m-d H:i:s"),
                    'userId' => ($_SESSION['userdata']['id']),
                    'moduleId' => $entityId,
                    'commentParent' => ($values['father']),
                    'status' => $values['status'] ?? ''
                );

                $comment = $this->commentRepository->addComment($mapper, $module);

                if ($comment) {
                    $mapper['id'] = $comment;

                    $currentUrl = CURRENT_URL;

                    switch ($module) {
                        case "ticket":
                            $subject = sprintf($this->language->__("email_notifications.new_comment_todo_with_type_subject"), $this->language->__("label.".$entity->type), $entity->id, $entity->headline);
                            $message = sprintf($this->language->__("email_notifications.new_comment_todo_with_type_message"), $_SESSION["userdata"]["name"], $this->language->__("label.".$entity->type), $entity->headline, $values['text']);
                            $linkLabel = $this->language->__("email_notifications.new_comment_todo_cta");
                            break;
                        case "project":
                            $subject = sprintf($this->language->__("email_notifications.new_comment_project_subject"), $entityId, $entity['name']);
                            $message = sprintf($this->language->__("email_notifications.new_comment_project_message"), $_SESSION["userdata"]["name"], $entity['name']);
                            $linkLabel = $this->language->__("email_notifications.new_comment_project_cta");
                            break;
                        default:
                            $subject = $this->language->__("email_notifications.new_comment_general_subject");
                            $message = sprintf($this->language->__("email_notifications.new_comment_general_message"), $_SESSION["userdata"]["name"]);
                            $linkLabel = $this->language->__("email_notifications.new_comment_general_cta");
                            break;
                    }


                    $notification = app()->make(notification::class);
                    $notification->url = array(
                        "url" => $currentUrl."&projectId=".$_SESSION['currentProject'],
                        "text" => $linkLabel
                    );

                    $notification->entity = $mapper;
                    $notification->module = "comments";
                    $notification->projectId = $_SESSION['currentProject'];
                    $notification->subject = $subject;
                    $notification->authorId = $_SESSION['userdata']['id'];
                    $notification->message = $message;

                    $this->projectService->notifyProjectUsers($notification);

                    return true;
                }
            }

            return false;
        }

        public function deleteComment($commentId)
        {

            return $this->commentRepository->deleteComment($commentId);
        }
    }

}
