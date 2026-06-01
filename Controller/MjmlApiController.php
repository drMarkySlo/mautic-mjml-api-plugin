<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMjmlApiBundle\Controller;

use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\EmailBundle\Entity\Email;
use MauticPlugin\GrapesJsBuilderBundle\Entity\GrapesJsBuilder;
use MauticPlugin\MauticMjmlApiBundle\Helper\MjmlCompiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MJML API Controller
 * 
 * Handles MJML email creation and editing via API
 */
class MjmlApiController extends CommonApiController
{
    /**
     * Create new email with MJML content
     * 
     * POST /api/emails/mjml/new
     * 
     * Required fields:
     * - name: Email name
     * - subject: Email subject
     * - mjml: MJML source code
     * - lists: Array of list IDs
     * 
     * Optional fields:
     * - template: Template name (default: null/blank)
     * - fromAddress, fromName, replyToAddress
     * - isPublished, language, emailType
     */
    public function createEmailAction(Request $request)
    {
        $data = $request->request->all();
        
        // Validate required fields
        if (empty($data['name'])) {
            return $this->returnError('name is required', Response::HTTP_BAD_REQUEST);
        }
        
        if (empty($data['subject'])) {
            return $this->returnError('subject is required', Response::HTTP_BAD_REQUEST);
        }
        
        if (empty($data['mjml'])) {
            return $this->returnError('mjml is required', Response::HTTP_BAD_REQUEST);
        }
        
        if (empty($data['lists']) || !is_array($data['lists'])) {
            return $this->returnError('lists array is required', Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $emailModel = $this->getModel('email');
            $em = $this->doctrine->getManager();
            
            // Compile MJML to HTML
            $mjmlCompiler = new MjmlCompiler();
            $compiledHtml = $mjmlCompiler->compile($data['mjml']);
            
            if (!$compiledHtml) {
                return $this->returnError('Failed to compile MJML', Response::HTTP_BAD_REQUEST);
            }
            
            // Create email entity
            $email = new Email();
            $email->setName($data['name']);
            $email->setSubject($data['subject']);
            $email->setCustomHtml($compiledHtml);
            $email->setEmailType($data['emailType'] ?? 'list');
            $email->setIsPublished($data['isPublished'] ?? false);
            $email->setLanguage($data['language'] ?? 'en');
            
            // Set template - use 'blank' for custom HTML emails
            // When using customHtml, Mautic needs 'blank' template to render properly
            if (!empty($data['template'])) {
                $email->setTemplate($data['template']);
            } else {
                // Default to 'blank' template for custom HTML
                $email->setTemplate('blank');
            }
            
            // Set sender info
            if (!empty($data['fromAddress'])) {
                $email->setFromAddress($data['fromAddress']);
            }
            if (!empty($data['fromName'])) {
                $email->setFromName($data['fromName']);
            }
            if (!empty($data['replyToAddress'])) {
                $email->setReplyToAddress($data['replyToAddress']);
            }
            
            // Handle Publish Up/Down
            // Določimo časovni pas Ljubljana
            $timezone = new \DateTimeZone('Europe/Ljubljana');
            
            if (!empty($data['publishUp'])) {
                try {
                    $email->setPublishUp(new \DateTime($data['publishUp'], $timezone));
                } catch (\Exception $e) {
                    // Ignore invalid date format
                }
            }
            if (!empty($data['publishDown'])) {
                try {
                    $email->setPublishDown(new \DateTime($data['publishDown'], $timezone));
                } catch (\Exception $e) {
                    // Ignore invalid date format
                }
            }
            
            // Handle UTM Tags
            if (isset($data['utmTags']) && is_array($data['utmTags'])) {
                $email->setUtmTags($data['utmTags']);
            }
            
            // Add lists to email BEFORE saving
            $listModel = $this->getModel('lead.list');
            foreach ($data['lists'] as $listId) {
                $list = $listModel->getEntity($listId);
                if ($list) {
                    $email->addList($list);
                }
            }
            
            // Save email with lists
            $emailModel->saveEntity($email);
            
            // Save MJML source in GrapesJS builder table
            // This ensures the email is editable in the visual builder
            $grapesJsBuilder = new GrapesJsBuilder();
            $grapesJsBuilder->setEmail($email);
            $grapesJsBuilder->setCustomMjml($data['mjml']);
            $em->persist($grapesJsBuilder);
            $em->flush();
            
            // Return response
            $view = $this->view(
                [
                    'email' => $emailModel->getEntity($email->getId()),
                ],
                Response::HTTP_CREATED
            );
            
            return $this->handleView($view);
            
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Edit existing email with MJML content
     * 
     * PATCH /api/emails/mjml/{id}/edit
     */
    public function editEmailAction(Request $request, $id)
    {
        $data = $request->request->all();
        
        try {
            $emailModel = $this->getModel('email');
            $em = $this->doctrine->getManager();
            
            // Get existing email
            $email = $emailModel->getEntity($id);
            if (!$email) {
                return $this->notFound();
            }
            
            // Update MJML if provided
            if (!empty($data['mjml'])) {
                // Compile MJML to HTML
                $mjmlCompiler = new MjmlCompiler();
                $compiledHtml = $mjmlCompiler->compile($data['mjml']);
                
                if (!$compiledHtml) {
                    return $this->returnError('Failed to compile MJML', Response::HTTP_BAD_REQUEST);
                }
                
                $email->setCustomHtml($compiledHtml);
                
                // Update MJML source in GrapesJS builder table
                $grapesJsRepo = $em->getRepository(GrapesJsBuilder::class);
                $grapesJsBuilder = $grapesJsRepo->findOneBy(['email' => $email]);
                
                if (!$grapesJsBuilder) {
                    $grapesJsBuilder = new GrapesJsBuilder();
                    $grapesJsBuilder->setEmail($email);
                }
                
                $grapesJsBuilder->setCustomMjml($data['mjml']);
                $em->persist($grapesJsBuilder);
            }
            
            // Update other fields if provided
            if (isset($data['name'])) {
                $email->setName($data['name']);
            }
            if (isset($data['subject'])) {
                $email->setSubject($data['subject']);
            }
            if (isset($data['isPublished'])) {
                $email->setIsPublished($data['isPublished']);
            }
            
            // Update Publish Dates
            // Določimo časovni pas Ljubljana
            $timezone = new \DateTimeZone('Europe/Ljubljana');
            
            if (array_key_exists('publishUp', $data)) {
                if (!empty($data['publishUp'])) {
                    try {
                        $email->setPublishUp(new \DateTime($data['publishUp'], $timezone));
                    } catch (\Exception $e) {
                        // Ignore invalid date format
                    }
                } else {
                    $email->setPublishUp(null);
                }
            }
            if (array_key_exists('publishDown', $data)) {
                if (!empty($data['publishDown'])) {
                    try {
                        $email->setPublishDown(new \DateTime($data['publishDown'], $timezone));
                    } catch (\Exception $e) {
                        // Ignore invalid date format
                    }
                } else {
                    $email->setPublishDown(null);
                }
            }
            
            // Update UTM Tags
            if (isset($data['utmTags']) && is_array($data['utmTags'])) {
                $email->setUtmTags($data['utmTags']);
            }
            
            // Update Sender Info
            if (isset($data['fromName'])) {
                $email->setFromName($data['fromName']);
            }
            if (isset($data['fromAddress'])) {
                $email->setFromAddress($data['fromAddress']);
            }
            if (isset($data['replyToAddress'])) {
                $email->setReplyToAddress($data['replyToAddress']);
            }
            
            // Update lists if provided
            if (isset($data['lists']) && is_array($data['lists'])) {
                $listModel = $this->getModel('lead.list');
                
                // Remove existing lists
                foreach ($email->getLists() as $list) {
                    $email->removeList($list);
                }
                
                // Add new lists
                foreach ($data['lists'] as $listId) {
                    $list = $listModel->getEntity($listId);
                    if ($list) {
                        $email->addList($list);
                    }
                }
            }
            
            // Save changes
            $emailModel->saveEntity($email);
            $em->flush();
            
            // Return response
            $view = $this->view(
                [
                    'email' => $emailModel->getEntity($email->getId()),
                ],
                Response::HTTP_OK
            );
            
            return $this->handleView($view);
            
        } catch (\Exception $e) {
            return $this->returnError($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}