doctrine2 orm 中文文档

## 文档分工翻译规则 ##

> 首先，你确认你要翻译的哪个类，找到类文件，把信息提交到Issue Tracking中。表明你要开始翻译此文件中的信息。

> 第一期，只要求翻译的public的方法和属性，翻译只需要替换原有的英文即可，如果你有心得，譬如添加样例代码也可以。使用的`DockBlock`语法下方有参考。

> 每周的要求只需要一个类文件的翻译，当然多多益善。完成后，还是回到ISSUE TRACKING中去修改状态。

> 你可以用SVN下到本地，然后翻译，定期提交，也可以在线翻译并提交。

## 翻译范例 ##
```

    /**
     * 方法简述Defines whether the query should make use of a query cache, if available.
     * 
     * 方法的具体介绍，譬如举例等等
     * @param boolean $bool  参数描述
     * @return @return 返回值描述 Query This query instance.
     */
    public function useQueryCache($bool)
    {
        $this->_useQueryCache = $bool;

        return $this;
    }
```



## Dockblock 语法介绍 ##
`DockBlock是一种C++风格的Php注释规则，开始用 "/**"，每行初始为"*"。文档的区块必须在你所要文档元素之前。任何没有初始位"*"的行都被忽略掉。`

To document function "foo()", place the DocBlock immediately before the function declaration:
```
    /**
     * 函数的简述，用最精炼的语言描述函数的功能
     */
    function foo()
    {
    }
```
This example will apply the DocBlock to "define('me',2);" instead of to "function foo()":
```
    /**
     * 简述
     *
     * 详细内容，与简述之间空一行。不过要注意的是DockBlock针对的是define('me',2);不是函数foo
     */
    define('me',2);
    function foo($param = me)
    {
    }
```



`一个DockBlock依次包含三个部分:`
  * Short Description 简述
  * Long Description  详述
  * Tags 标签

```
     /**
     * 简述
     *
     * 详述内容，最后结束于
     * 此。起第二段落，用空行隔开。
     *
     * 第二段落
     */
```

使用html标签p来表示段落
```
     /**
     * Short desc
     *
     * <p>Long description first sentence starts here
     * and continues on this line for a while
     * finally concluding here at the end of
     * this paragraph</p>
     * This text is completely ignored! it is not enclosed in p tags
     * <p>This is a new paragraph</p>
     */
```

更多的在详情部分的html标签
```
 <b> -- emphasize/bold text
 <code> -- Use this to surround php code, some converters will highlight it
 <br> -- hard line break, may be ignored by some converters
 <i> -- italicize/mark as important
 <kbd> -- denote keyboard input/screen display
 <li> -- list item
 <ol> -- ordered list
 <p> -- If used to enclose all paragraphs, otherwise it will be considered text
 <pre> -- Preserve line breaks and spacing, and assume all tags are text (like XML's CDATA)
 <samp> -- 表示样例或者举例
 <ul> -- unordered list
 <var> -- 表示变量
```

Tags的用法，此处列出了所有的tag
```
/**
     * The short description
     *
     * As many lines of extendend description as you want {@link element}
     * links to an element
     * {@link http://www.example.com Example hyperlink inline link} links to
     * a website. The inline
     * source tag displays function source code in the description:
     * {@source } 
     * 
     * In addition, in version 1.2+ one can link to extended documentation like this
     * documentation using {@tutorial phpDocumentor/phpDocumentor.howto.pkg}
     * In a method/class var, {@inheritdoc may be used to copy documentation from}
     * the parent method
     * {@internal 
     * This paragraph explains very detailed information that will only
     * be of use to advanced developers, and can contain
     * {@link http://www.example.com Other inline links!} as well as text}}}
     *
     * Here are the tags:
     *
     * @abstract     表示抽象类，方法
     * @access       public or private 访问范围修饰符
     * @author       author name <author@email> 作者
     * @copyright    name date 版权信息
     * @deprecated   description 表示过期，不再延用
     * @deprec       alias for deprecated 上面的缩写
     * @example      /path/to/example 举例，指定一个路径
     * @exception    Javadoc-compatible, use as needed，异常
     * @global       type $globalvarname 表示全局量或者函数
       or
     * @global       type description of global variable usage in a function
     * @ignore       忽略
     * @internal     private information for advanced developers only 私有信息用于高级开发员
     * @param        type [$varname] description  参数：类型 参数名 参数描述
     * @return       type description  返回值：类型 描述
     * @link         URL  链接到一个地址
     * @name         procpagealias
       or
     * @name         $globalvaralias
     * @magic        phpdoc.de compatibility
     * @package      package name
     * @see          name of another element that can be documented,
     *                produces a link to it in the documentation 参考什么什么
     * @since        a version or a date 版本或者日期
     * @static       静态
     * @staticvar    type description of static variable usage in a function
     * @subpackage    sub package name, groupings inside of a project
     * @throws       Javadoc-compatible, use as needed
     * @todo         phpdoc.de compatibility 表示需要代码优化或者重构
     * @var        type    a data type for a class variable
     * @version    version 版本
     */
    function if_there_is_an_inline_source_tag_this_must_be_a_function()
    {
    // ...
    }
```

如果你想加入到翻译组来,请邮件联系我：kendoctor@163.com