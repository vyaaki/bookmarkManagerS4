<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\BookMark;
use App\Repository\BookMarkRepository;
use DateTime;
use Exception;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class BookMarkAdmin extends AbstractAdmin
{

    /**
     * @var HttpClientInterface
     */
    private HttpClientInterface $httpClient;
    /**
     * @var UrlHelper
     */
    private UrlHelper $urlHelper;

    /**
     * @var string
     */
    private string $htmlContent;
    /**
     * @var BookMarkRepository
     */
    private BookMarkRepository $bookmarkRepository;

    public function __construct($code, $class, $baseControllerName, HttpClientInterface $httpClient, UrlHelper $urlHelper, BookMarkRepository $bookMarkRepository)
    {
        $this->httpClient = $httpClient;
        $this->urlHelper = $urlHelper;
        $this->bookmarkRepository = $bookMarkRepository;
        parent::__construct($code, $class, $baseControllerName);

    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('creationDate')
            ->add('favicon')
            ->add('url')
            ->add('header')
            ->add('metaDescription')
            ->add('metaKeywords');
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->add('creationDate')
            ->add('favicon')
            ->add('url')
            ->add('header')
            ->add('metaDescription')
            ->add('metaKeywords')
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('url');
    }

    protected function configureShowFields(ShowMapper $showMapper): void
    {
        $showMapper
            ->add('creationDate')
            ->add('favicon')
            ->add('url')
            ->add('header')
            ->add('metaDescription')
            ->add('metaKeywords');
    }

    /**
     * @param ErrorElement $errorElement
     * @param BookMark $object
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function validate(ErrorElement $errorElement, $object)
    {
        if ($this->bookmarkRepository->count(['url' => $object->getUrl()]) > 0) {
            $errorElement->addViolation('Already in DB');
            return;
        }
        try {
            $response = $this->httpClient->request('GET', $object->getUrl());
        } catch (TransportExceptionInterface $e) {
            $errorElement->addViolation($e->getMessage());
            return;
        }

        try {
            $this->htmlContent = $response->getContent();
        } catch (Exception $e) {
            $errorElement->addViolation($e->getMessage());
            return;
        }
    }

    /**
     * @param BookMark $object
     */
    public function prePersist($object)
    {
        $crawler = new Crawler($this->htmlContent);
        $object->setCreationDate(new DateTime());
        if ($crawler->filter('head > title')->count() > 0) {
            $object->setHeader($crawler->filter('head > title')->text());
        }
        if ($crawler->filterXPath('//link[contains(@rel, "icon")]')->count() > 0) {
            $favicon = $crawler->filterXPath('//link[contains(@rel, "icon")]')->first()->attr('href');
            $object->setFavicon(parse_url($object->getUrl())['host'] . $favicon);
        }
        if ($crawler->filterXPath('//meta[contains(@name, "description")]')->count() > 0) {
            $object->setMetaDescription($crawler->filterXPath('//meta[contains(@name, "description")]')->first()->attr('content'));
        }
        if ($crawler->filterXPath('//meta[contains(@name, "Keywords")]')->count() > 0) {
            $object->setMetaKeywords($crawler->filterXPath('//meta[contains(@name, "Keywords")]')->first()->attr('content'));
        }
    }
}
