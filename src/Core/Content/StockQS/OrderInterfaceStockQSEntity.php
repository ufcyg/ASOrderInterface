<?php declare(strict_types=1);

namespace ASOrderInterface\Core\Content\StockQS;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderInterfaceStockQSEntity extends Entity
{
    use EntityIdTrait;
    
    /** @var string */
    protected $productId;
    /**  @var int */
    protected $faulty;
    /** @var int */
    protected $clarification;
    /** @var int */
    protected $postprocessing;
    /** @var int */
    protected $other;

    /**
     * Get the value of productId
     */ 
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * Set the value of productId
     *
     * @return  self
     */ 
    public function setProductId($productId)
    {
        $this->productId = $productId;

        return $this;
    }

    /**
     * Get the value of faulty
     */ 
    public function getFaulty()
    {
        return $this->faulty;
    }

    /**
     * Set the value of faulty
     *
     * @return  self
     */ 
    public function setFaulty($faulty)
    {
        $this->faulty = $faulty;

        return $this;
    }

    /**
     * Get the value of clarification
     */ 
    public function getClarification()
    {
        return $this->clarification;
    }

    /**
     * Set the value of clarification
     *
     * @return  self
     */ 
    public function setClarification($clarification)
    {
        $this->clarification = $clarification;

        return $this;
    }

    /**
     * Get the value of postprocessing
     */ 
    public function getPostprocessing()
    {
        return $this->postprocessing;
    }

    /**
     * Set the value of postprocessing
     *
     * @return  self
     */ 
    public function setPostprocessing($postprocessing)
    {
        $this->postprocessing = $postprocessing;

        return $this;
    }

    /**
     * Get the value of expiredMhd
     */ 
    public function getExpiredMhd()
    {
        return $this->expiredMhd;
    }

    /**
     * Set the value of expiredMhd
     *
     * @return  self
     */ 
    public function setExpiredMhd($expiredMhd)
    {
        $this->expiredMhd = $expiredMhd;

        return $this;
    }

    /**
     * Get the value of other
     */ 
    public function getOther()
    {
        return $this->other;
    }

    /**
     * Set the value of other
     *
     * @return  self
     */ 
    public function setOther($other)
    {
        $this->other = $other;

        return $this;
    }
}

// (new IdField('id','id'))->addFlags(new Required(), new PrimaryKey()) ,
//                 new StringField('product_id','productId'),
//                 new IntField('faulty','faulty'),
//                 new IntField('clarification','clarification'),
//                 new IntField('postprocessing','postprocessing'),
//                 new IntField('expired_mhd','expiredMhd'),
//                 new IntField('other','other')