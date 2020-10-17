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
            ->add('favicon', 'favicon')
            ->add('url')
            ->add('header')
            ->add('metaDescription')
            ->add('metaKeywords')
            ->add('_action', null, [
                'actions' => [
                    'show' => [],
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
            ->add('favicon', 'favicon')
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
        $parsedUrl = parse_url($object->getUrl());
        $baseURL = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        $object->setHeader($this->parseHeader($crawler));
        $object->setFavicon($this->parseFavicon($crawler, $baseURL));
        $object->setMetaDescription($this->parseDescription($crawler));
        $object->setMetaKeywords($this->parseKeywords($crawler));
    }

    private function parseHeader(Crawler $crawler)
    {
        $crawlerFiltered = $crawler->filter('head > title');
        return $crawlerFiltered->first()->text();
    }

    private function parseDescription(Crawler $crawler)
    {
        $patternArr = ['//meta[@name="description"]', '//meta[contains(@name, "Description")]', '//meta[contains(@name, "description")]', '//meta[contains(@property, "description")]', '//meta[contains(@property, "Description")]'];
        return $this->getMetaContent($patternArr, $crawler);
    }

    private function parseKeywords(Crawler $crawler)
    {
        $patternArr = ['//meta[@name="keywords"]', '//meta[contains(@name, "Keywords")]', '//meta[contains(@name, "keywords")]', '//meta[contains(@property, "keywords")]', '//meta[contains(@property, "Keywords")]'];
        return $this->getMetaContent($patternArr, $crawler);
    }

    private function parseFavicon(Crawler $crawlerParsed, string $baseURL): ?string
    {
        $patternArr = ['//link[contains(@rel, "icon")]', '//link[contains(@href, "favicon")]'];
        foreach ($patternArr as $pattern) {
            if ($crawlerParsed->filterXPath($pattern)->count() > 0) {
                $favicon = $crawlerParsed->filterXPath($pattern)->first()->attr('href');
                if (preg_match('@^(http|https)@', $favicon)) {
                    $result = $favicon;
                } else {
                    $result = $baseURL . $favicon;
                }
                return $result;
            }
        }
        return null;
    }

    /**
     * @param array $patternArr
     * @param Crawler $crawler
     * @return string|null
     */
    private function getMetaContent(array $patternArr, Crawler $crawler): ?string
    {
        foreach ($patternArr as $pattern) {
            $crawlerFiltered = $crawler->filterXPath($pattern);
            if ($crawlerFiltered->count() > 0) {
                return $crawlerFiltered->first()->attr('content');
            }
        }
        return null;
    }
}
