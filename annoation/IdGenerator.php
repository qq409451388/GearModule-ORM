<?php

class IdGenerator extends BuildAnnotation
{
    /**
     * @var Clazz<EzIdClient>
     */
    public $clazz;

    public $idGroup;

    public function combine($values) {
        $this->idGroup = $values['idGroup']??"default";
        DBC::assertNotEmpty($values['idClient'], "[Anno] IdGenerator params idClient is empty!");
        $this->clazz = Clazz::get($values['idClient']);
    }

    public function constTarget()
    {
        return AnnoElementType::TYPE_CLASS;
    }

    public function constStruct()
    {
        return AnnoValueTypeEnum::TYPE_RELATION;
    }

    public function constAspect()
    {
        return IdGeneratorAspect::class;
    }
}
